<?php
declare (strict_types = 1);

namespace app\command;

use app\admin\service\ContentAdminService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

/**
 * 重算短剧/小说展示统计字段
 */
class ContentRebuildStats extends Command
{
    protected function configure()
    {
        $this->setName('content:rebuild-stats')
            ->setDescription('重算短剧/小说总集(章)数、总字数及章节字数')
            ->addOption('limit', 'l', Option::VALUE_OPTIONAL, '单次扫描作品数（短剧+小说各自上限）', '500')
            ->addOption('dry-run', null, Option::VALUE_NONE, '仅统计，不落库');
    }

    protected function execute(Input $input, Output $output)
    {
        $limit = max(1, min(5000, (int)$input->getOption('limit')));
        $dryRun = (bool)$input->getOption('dry-run');

        $service = new ContentAdminService();
        $result = $service->rebuildDisplayStats($limit, $dryRun);
        $output->writeln(sprintf(
            '[content:rebuild-stats] dry_run=%d drama_scanned=%d drama_total_episodes_fixed=%d novel_scanned=%d novel_total_chapters_fixed=%d novel_word_count_fixed=%d chapter_word_count_fixed=%d executed_at=%s',
            (int)($result['dry_run'] ?? 0),
            (int)($result['drama_scanned'] ?? 0),
            (int)($result['drama_total_episodes_fixed'] ?? 0),
            (int)($result['novel_scanned'] ?? 0),
            (int)($result['novel_total_chapters_fixed'] ?? 0),
            (int)($result['novel_word_count_fixed'] ?? 0),
            (int)($result['chapter_word_count_fixed'] ?? 0),
            (string)($result['executed_at'] ?? date('Y-m-d H:i:s'))
        ));

        return 0;
    }
}
