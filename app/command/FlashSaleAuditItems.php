<?php
declare (strict_types = 1);

namespace app\command;

use app\api\model\Drama;
use app\api\model\Novel;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;

/**
 * 巡检并可选下线失效的秒杀商品
 */
class FlashSaleAuditItems extends Command
{
    /**
     * 注册命令名、描述与参数
     */
    protected function configure()
    {
        $this->setName('flash-sale:audit-items')
            ->setDescription('巡检秒杀商品引用有效性，可选择自动下线失效商品')
            ->addOption('activity-id', 'a', Option::VALUE_OPTIONAL, '仅巡检指定活动ID（默认全部）', '0')
            ->addOption('limit', 'l', Option::VALUE_OPTIONAL, '单次最多扫描条数', '2000')
            ->addOption('fix', 'f', Option::VALUE_NONE, '自动将失效商品置为停用(status=0)');
    }

    /**
     * 执行巡检
     */
    protected function execute(Input $input, Output $output)
    {
        $activityId = max(0, (int)$input->getOption('activity-id'));
        $limit = max(1, min(20000, (int)$input->getOption('limit')));
        $fix = (bool)$input->getOption('fix');

        $query = Db::name('flash_sale_item')
            ->where('status', 1)
            ->order('id', 'asc')
            ->limit($limit);
        if ($activityId > 0) {
            $query->where('activity_id', $activityId);
        }
        $rows = $query->field('id,activity_id,goods_type,goods_id,status')->select()->toArray();

        $dramaIds = [];
        $novelIds = [];
        foreach ($rows as $row) {
            $goodsType = (int)($row['goods_type'] ?? 0);
            $goodsId = (int)($row['goods_id'] ?? 0);
            if ($goodsType === 10 && $goodsId > 0) {
                $dramaIds[] = $goodsId;
            } elseif ($goodsType === 20 && $goodsId > 0) {
                $novelIds[] = $goodsId;
            }
        }
        $validDramaMap = [];
        $validNovelMap = [];
        if (!empty($dramaIds)) {
            $validDramaMap = array_flip(array_map(
                'intval',
                Drama::whereIn('id', array_values(array_unique($dramaIds)))
                    ->where('status', 1)
                    ->column('id')
            ));
        }
        if (!empty($novelIds)) {
            $validNovelMap = array_flip(array_map(
                'intval',
                Novel::whereIn('id', array_values(array_unique($novelIds)))
                    ->where('status', 1)
                    ->column('id')
            ));
        }

        $invalidItemIds = [];
        $reasonStats = [
            'invalid_goods_type' => 0,
            'invalid_goods_id' => 0,
            'drama_not_found_or_offline' => 0,
            'novel_not_found_or_offline' => 0,
        ];
        foreach ($rows as $row) {
            $itemId = (int)($row['id'] ?? 0);
            $goodsType = (int)($row['goods_type'] ?? 0);
            $goodsId = (int)($row['goods_id'] ?? 0);
            $reason = '';
            if (!in_array($goodsType, [10, 20], true)) {
                $reason = 'invalid_goods_type';
            } elseif ($goodsId <= 0) {
                $reason = 'invalid_goods_id';
            } elseif ($goodsType === 10 && !isset($validDramaMap[$goodsId])) {
                $reason = 'drama_not_found_or_offline';
            } elseif ($goodsType === 20 && !isset($validNovelMap[$goodsId])) {
                $reason = 'novel_not_found_or_offline';
            }
            if ($reason === '') {
                continue;
            }
            $reasonStats[$reason] = (int)($reasonStats[$reason] ?? 0) + 1;
            if ($itemId > 0) {
                $invalidItemIds[] = $itemId;
            }
        }

        $invalidItemIds = array_values(array_unique($invalidItemIds));
        $disabled = 0;
        if ($fix && !empty($invalidItemIds)) {
            $disabled = (int)Db::name('flash_sale_item')
                ->whereIn('id', $invalidItemIds)
                ->where('status', 1)
                ->update([
                    'status' => 0,
                    'update_time' => date('Y-m-d H:i:s'),
                ]);
        }

        $output->writeln(sprintf(
            '[flash-sale:audit-items] activity_id=%d scanned=%d invalid=%d fixed=%d mode=%s executed_at=%s',
            $activityId,
            count($rows),
            count($invalidItemIds),
            $disabled,
            $fix ? 'fix' : 'dry-run',
            date('Y-m-d H:i:s')
        ));
        $output->writeln(sprintf(
            '[flash-sale:audit-items] reasons invalid_goods_type=%d invalid_goods_id=%d drama_not_found_or_offline=%d novel_not_found_or_offline=%d',
            (int)$reasonStats['invalid_goods_type'],
            (int)$reasonStats['invalid_goods_id'],
            (int)$reasonStats['drama_not_found_or_offline'],
            (int)$reasonStats['novel_not_found_or_offline']
        ));
        if (!$fix && !empty($invalidItemIds)) {
            $sample = implode(',', array_slice($invalidItemIds, 0, 50));
            $output->writeln('[flash-sale:audit-items] sample_invalid_item_ids=' . $sample);
            $output->writeln('[flash-sale:audit-items] tip=加 --fix 可自动下线失效商品');
        }
        return 0;
    }
}

