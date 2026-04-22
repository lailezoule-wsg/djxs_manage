<?php
declare (strict_types = 1);

namespace app\command;

use app\api\service\OrderService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;

/**
 * 订单列表/详情返回结构契约检查
 */
class OrderContractCheck extends Command
{
    /**
     * 注册命令名、描述与参数
     */
    protected function configure()
    {
        $this->setName('order:contract-check')
            ->setDescription('检查 order/list 与 order/detail 返回字段一致性')
            ->addOption('user', 'u', Option::VALUE_OPTIONAL, '指定用户ID')
            ->addOption('order', 'o', Option::VALUE_OPTIONAL, '指定订单ID（可选）');
    }

    /**
     * 执行订单接口字段契约检查
     */
    protected function execute(Input $input, Output $output)
    {
        $userId = (int)$input->getOption('user');
        $orderId = (int)$input->getOption('order');

        if ($userId <= 0 || $orderId <= 0) {
            $latest = Db::name('order')->where('user_id', '>', 0)->order('id', 'desc')->find();
            if (!$latest) {
                $output->writeln('[order:contract-check] no order data found, skip');
                return 0;
            }
            if ($userId <= 0) {
                $userId = (int)$latest['user_id'];
            }
            if ($orderId <= 0) {
                $orderId = (int)$latest['id'];
            }
        }

        $service = new OrderService();
        $listResult = $service->list($userId, ['page' => 1, 'limit' => 1]);
        $list = (array)($listResult['list'] ?? []);
        if (empty($list)) {
            $output->writeln(sprintf('[order:contract-check] user=%d has no orders', $userId));
            return 0;
        }

        $listRow = (array)$list[0];
        $detailRow = (array)$service->detail($userId, $orderId);

        [$missingInDetail, $missingInList] = $this->diffKeys($listRow, $detailRow);
        [$goodsMissingInDetail, $goodsMissingInList] = $this->diffKeys(
            (array)($listRow['goods'] ?? []),
            (array)($detailRow['goods'] ?? [])
        );

        if (
            empty($missingInDetail)
            && empty($missingInList)
            && empty($goodsMissingInDetail)
            && empty($goodsMissingInList)
        ) {
            $output->writeln(sprintf(
                '[order:contract-check] PASS user=%d order=%d top_keys=%d goods_keys=%d',
                $userId,
                $orderId,
                count(array_keys($listRow)),
                count(array_keys((array)($listRow['goods'] ?? [])))
            ));
            return 0;
        }

        $output->writeln(sprintf('[order:contract-check] FAIL user=%d order=%d', $userId, $orderId));
        if (!empty($missingInDetail)) {
            $output->writeln(' - missing in detail: ' . implode(',', $missingInDetail));
        }
        if (!empty($missingInList)) {
            $output->writeln(' - missing in list: ' . implode(',', $missingInList));
        }
        if (!empty($goodsMissingInDetail)) {
            $output->writeln(' - goods missing in detail: ' . implode(',', $goodsMissingInDetail));
        }
        if (!empty($goodsMissingInList)) {
            $output->writeln(' - goods missing in list: ' . implode(',', $goodsMissingInList));
        }
        return 1;
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function diffKeys(array $left, array $right): array
    {
        $leftKeys = array_keys($left);
        $rightKeys = array_keys($right);
        sort($leftKeys);
        sort($rightKeys);
        $missingInRight = array_values(array_diff($leftKeys, $rightKeys));
        $missingInLeft = array_values(array_diff($rightKeys, $leftKeys));
        return [$missingInRight, $missingInLeft];
    }
}

