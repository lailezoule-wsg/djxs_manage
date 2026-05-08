<?php
require __DIR__ . '/../vendor/autoload.php';

$config = [
    'type'     => 'mysql',
    'hostname' => 'mysql-djxs',
    'database' => 'djxs_manage',
    'username' => 'djxs_user',
    'password' => 'djxs@pass@2026',
    'hostport' => '3306',
    'charset'  => 'utf8mb4',
    'prefix'   => 'djxs_',
];

try {
    $dsn = "mysql:host={$config['hostname']};port={$config['hostport']};dbname={$config['database']};charset={$config['charset']}";
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "Creating djxs_paqu_source_category table...\n";
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
    $pdo->exec($sql);
    echo "Table djxs_paqu_source_category created successfully\n";

    echo "\nAdding columns to paqu_source table...\n";
    
    $columns = [
        ['default_category_id', 'int DEFAULT NULL COMMENT "默认分类ID"'],
        ['category_rules', 'text COLLATE utf8mb4_unicode_ci COMMENT "分类映射规则"'],
        ['tag_rules', 'text COLLATE utf8mb4_unicode_ci COMMENT "标签提取规则"'],
    ];
    
    foreach ($columns as $col) {
        try {
            $sql = "ALTER TABLE `djxs_paqu_source` ADD COLUMN IF NOT EXISTS `{$col[0]}` {$col[1]}";
            $pdo->exec($sql);
            echo "Column {$col[0]} added successfully\n";
        } catch (Exception $e) {
            echo "Column {$col[0]} may already exist: " . $e->getMessage() . "\n";
        }
    }

    echo "\nAll migrations completed successfully!\n";
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage() . "\n");
}
?>