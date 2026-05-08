<?php
declare (strict_types = 1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

class MigratePaquCommand extends Command
{
    protected function configure()
    {
        $this->setName('migrate:paqu')
             ->setDescription('Migrate paqu source category tables');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('Starting paqu source migration...');
        
        try {
            $this->createPaquSourceCategoryTable($output);
            $this->addColumnsToPaquSource($output);
            $this->addChapterColumnsToCategoryTable($output);
            $output->writeln('<info>Migration completed successfully!</info>');
        } catch (\Exception $e) {
            $output->writeln('<error>Migration failed: ' . $e->getMessage() . '</error>');
        }
    }

    private function createPaquSourceCategoryTable(Output $output)
    {
        $output->writeln('Creating djxs_paqu_source_category table...');
        
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `djxs_paqu_source_category` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `source_id` int NOT NULL COMMENT '数据源ID',
  `category_id` int NOT NULL COMMENT '系统分类ID',
  `list_url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '该分类的列表页URL',
  `page_param` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'page' COMMENT '分页参数名',
  `page_start` int DEFAULT '1' COMMENT '分页起始值',
  `sort` int DEFAULT '0' COMMENT '排序',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_source_category` (`source_id`, `category_id`),
  KEY `idx_source` (`source_id`),
  KEY `idx_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='数据源分类URL映射表';
SQL;
        
        Db::execute($sql);
        $output->writeln('<info>Table djxs_paqu_source_category created successfully</info>');
    }

    private function addColumnsToPaquSource(Output $output)
    {
        $output->writeln('Adding columns to djxs_paqu_source table...');
        
        $columns = [
            ['default_category_id', 'int DEFAULT NULL COMMENT "默认分类ID"'],
            ['category_rules', 'text COLLATE utf8mb4_unicode_ci COMMENT "分类映射规则"'],
            ['tag_rules', 'text COLLATE utf8mb4_unicode_ci COMMENT "标签提取规则"'],
        ];
        
        foreach ($columns as $col) {
            try {
                $sql = "ALTER TABLE `djxs_paqu_source` ADD COLUMN IF NOT EXISTS `{$col[0]}` {$col[1]}";
                Db::execute($sql);
                $output->writeln("<info>Column {$col[0]} added successfully</info>");
            } catch (\Exception $e) {
                $output->writeln("<warning>Column {$col[0]} may already exist: " . $e->getMessage() . "</warning>");
            }
        }
    }

    private function addChapterColumnsToCategoryTable(Output $output)
    {
        $output->writeln('Adding chapter columns to djxs_paqu_source_category table...');
        
        $columns = [
            ['chapter_url_pattern', 'text COLLATE utf8mb4_unicode_ci COMMENT "章节URL模板"'],
            ['chapter_parse_rules', 'text COLLATE utf8mb4_unicode_ci COMMENT "章节列表提取规则"'],
            ['content_parse_rules', 'text COLLATE utf8mb4_unicode_ci COMMENT "内容提取规则"'],
        ];
        
        foreach ($columns as $col) {
            try {
                $sql = "ALTER TABLE `djxs_paqu_source_category` ADD COLUMN IF NOT EXISTS `{$col[0]}` {$col[1]}";
                Db::execute($sql);
                $output->writeln("<info>Column {$col[0]} added successfully</info>");
            } catch (\Exception $e) {
                $output->writeln("<warning>Column {$col[0]} may already exist: " . $e->getMessage() . "</warning>");
            }
        }
    }
}
?>