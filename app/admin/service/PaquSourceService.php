<?php
declare (strict_types = 1);

namespace app\admin\service;

use app\admin\model\PaquSource;
use app\admin\model\PaquSourceCategory;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 数据源服务
 */
class PaquSourceService extends BaseAdminService
{
    public function list(array $params, int $page, int $pageSize): array
    {
        $query = PaquSource::where('status', '<>', -1);
        
        if (!empty($params['name'])) {
            $query->whereLike('name', '%' . $params['name'] . '%');
        }
        if (isset($params['type']) && in_array($params['type'], [PaquSource::TYPE_NOVEL, PaquSource::TYPE_DRAMA])) {
            $query->where('type', (int)$params['type']);
        }
        if (isset($params['status']) && in_array($params['status'], [PaquSource::STATUS_DISABLED, PaquSource::STATUS_ENABLED])) {
            $query->where('status', (int)$params['status']);
        }
        
        return $this->paginateToArray($query, $page, $pageSize);
    }

    public function create(array $payload): int
    {
        $this->validatePayload($payload);
        
        $data = [
            'name' => $payload['name'],
            'type' => (int)$payload['type'],
            'base_url' => $payload['base_url'],
            'charset' => $payload['charset'] ?? 'GBK',
            'timeout' => $payload['timeout'] ?? 30,
            'request_delay' => $payload['request_delay'] ?? 500,
            'default_category_id' => $payload['default_category_id'] ?? null,
            'list_url_pattern' => $payload['list_url_pattern'] ?? '',
            'detail_url_pattern' => $payload['detail_url_pattern'] ?? '',
            'list_parse_rules' => $payload['list_parse_rules'] ?? '',
            'chapter_parse_rules' => $payload['chapter_parse_rules'] ?? '',
            'content_parse_rules' => $payload['content_parse_rules'] ?? '',
            'parse_rules' => isset($payload['parse_rules']) ? json_encode($payload['parse_rules'], JSON_UNESCAPED_UNICODE) : '{}',
            'headers' => isset($payload['headers']) ? json_encode($payload['headers'], JSON_UNESCAPED_UNICODE) : '{}',
            'cookie' => $payload['cookie'] ?? '',
            'tag_rules' => isset($payload['tag_rules']) ? $payload['tag_rules'] : '[]',
            'status' => isset($payload['status']) ? (int)$payload['status'] : PaquSource::STATUS_ENABLED,
            'create_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s'),
        ];
        
        Db::startTrans();
        try {
            $id = (int)Db::name('paqu_source')->insertGetId($data);
            
            if (isset($payload['categories']) && is_array($payload['categories'])) {
                $this->saveCategories($id, $payload['categories']);
            }
            
            Db::commit();
            return $id;
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    public function update(int $id, array $payload): void
    {
        $this->assertExists('paqu_source', $id);
        
        $data = [];
        if (isset($payload['name'])) {
            $data['name'] = $payload['name'];
        }
        if (isset($payload['type']) && in_array($payload['type'], [PaquSource::TYPE_NOVEL, PaquSource::TYPE_DRAMA])) {
            $data['type'] = (int)$payload['type'];
        }
        if (isset($payload['base_url'])) {
            $data['base_url'] = $payload['base_url'];
        }
        if (isset($payload['charset'])) {
            $data['charset'] = $payload['charset'];
        }
        if (isset($payload['timeout'])) {
            $data['timeout'] = (int)$payload['timeout'];
        }
        if (isset($payload['request_delay'])) {
            $data['request_delay'] = (int)$payload['request_delay'];
        }
        if (isset($payload['default_category_id'])) {
            $data['default_category_id'] = $payload['default_category_id'] ?: null;
        }
        if (isset($payload['list_url_pattern'])) {
            $data['list_url_pattern'] = $payload['list_url_pattern'];
        }
        if (isset($payload['detail_url_pattern'])) {
            $data['detail_url_pattern'] = $payload['detail_url_pattern'];
        }
        if (isset($payload['list_parse_rules'])) {
            $data['list_parse_rules'] = $payload['list_parse_rules'];
        }
        if (isset($payload['chapter_parse_rules'])) {
            $data['chapter_parse_rules'] = $payload['chapter_parse_rules'];
        }
        if (isset($payload['content_parse_rules'])) {
            $data['content_parse_rules'] = $payload['content_parse_rules'];
        }
        if (isset($payload['parse_rules'])) {
            $data['parse_rules'] = json_encode($payload['parse_rules'], JSON_UNESCAPED_UNICODE);
        }
        if (isset($payload['headers'])) {
            $data['headers'] = json_encode($payload['headers'], JSON_UNESCAPED_UNICODE);
        }
        if (isset($payload['cookie'])) {
            $data['cookie'] = $payload['cookie'];
        }
        if (isset($payload['tag_rules'])) {
            $data['tag_rules'] = $payload['tag_rules'];
        }
        if (isset($payload['status']) && in_array($payload['status'], [PaquSource::STATUS_DISABLED, PaquSource::STATUS_ENABLED])) {
            $data['status'] = (int)$payload['status'];
        }
        $data['update_time'] = date('Y-m-d H:i:s');
        
        Db::startTrans();
        try {
            Db::name('paqu_source')->where('id', $id)->update($data);
            
            if (isset($payload['categories']) && is_array($payload['categories'])) {
                $this->saveCategories($id, $payload['categories']);
            }
            
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    public function delete(int $id): void
    {
        $this->assertExists('paqu_source', $id);
        
        $count = Db::name('paqu_task')->where('source_id', $id)->where('status', '<>', -1)->count();
        if ($count > 0) {
            throw new ValidateException('该数据源下存在任务，无法删除');
        }
        
        Db::name('paqu_source')->where('id', $id)->update([
            'status' => -1,
            'update_time' => date('Y-m-d H:i:s'),
        ]);
        
        PaquSourceCategory::where('source_id', $id)->delete();
    }

    public function getCategoryList(int $sourceId): array
    {
        return PaquSourceCategory::where('source_id', $sourceId)
            ->where('status', PaquSourceCategory::STATUS_ENABLED)
            ->order('sort', 'desc')
            ->select()
            ->toArray();
    }

    public function saveCategories(int $sourceId, array $categories): void
    {
        Db::startTrans();
        try {
            PaquSourceCategory::where('source_id', $sourceId)->delete();
            
            foreach ($categories as $item) {
                PaquSourceCategory::create([
                    'source_id' => $sourceId,
                    'category_id' => (int)$item['category_id'],
                    'list_url' => $item['list_url'],
                    'page_param' => $item['page_param'] ?? 'page',
                    'page_start' => (int)($item['page_start'] ?? 1),
                    'page_end' => (int)($item['page_end'] ?? 0),
                    'chapter_url_pattern' => $item['chapter_url_pattern'] ?? '',
                    'chapter_parse_rules' => $item['chapter_parse_rules'] ?? '',
                    'content_parse_rules' => $item['content_parse_rules'] ?? '',
                    'sort' => (int)($item['sort'] ?? 0),
                    'status' => (int)($item['status'] ?? PaquSourceCategory::STATUS_ENABLED),
                    'create_time' => date('Y-m-d H:i:s'),
                    'update_time' => date('Y-m-d H:i:s'),
                ]);
            }
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    public function test(int $id): array
    {
        $source = PaquSource::find($id);
        if (!$source) {
            throw new ValidateException('数据源不存在');
        }
        
        $baseUrl = $source['base_url'];
        if (empty($baseUrl)) {
            throw new ValidateException('数据源基础URL为空');
        }
        
        try {
            $ch = curl_init($baseUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $source['timeout'] ?? 10);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
            
            $headers = json_decode($source['headers'] ?? '{}', true);
            if (is_array($headers)) {
                $headerArray = [];
                foreach ($headers as $key => $value) {
                    $headerArray[] = "$key: $value";
                }
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);
            }
            
            $cookie = $source['cookie'] ?? '';
            if (!empty($cookie)) {
                curl_setopt($ch, CURLOPT_COOKIE, $cookie);
            }
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            return [
                'id' => $id,
                'url' => $baseUrl,
                'http_code' => $httpCode,
                'status' => $httpCode >= 200 && $httpCode < 400 ? 'success' : 'failed',
                'message' => $httpCode >= 200 && $httpCode < 400 ? '连接成功' : '连接失败',
                'checked_at' => date('Y-m-d H:i:s'),
            ];
        } catch (\Throwable $e) {
            return [
                'id' => $id,
                'url' => $baseUrl,
                'http_code' => 0,
                'status' => 'failed',
                'message' => '连接异常: ' . $e->getMessage(),
                'checked_at' => date('Y-m-d H:i:s'),
            ];
        }
    }

    public function detail(int $id): array
    {
        $source = PaquSource::find($id);
        if (!$source) {
            throw new ValidateException('数据源不存在');
        }
        
        $result = $source->toArray();
        $result['categories'] = $this->getCategoryList($id);
        
        return $result;
    }

    private function validatePayload(array $payload): void
    {
        $name = trim((string)($payload['name'] ?? ''));
        if ($name === '') {
            throw new ValidateException('数据源名称不能为空');
        }
        
        $type = (int)($payload['type'] ?? 0);
        if (!in_array($type, [PaquSource::TYPE_NOVEL, PaquSource::TYPE_DRAMA])) {
            throw new ValidateException('请选择正确的类型');
        }
        
        $baseUrl = trim((string)($payload['base_url'] ?? ''));
        if ($baseUrl === '') {
            throw new ValidateException('基础URL不能为空');
        }
        if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            throw new ValidateException('基础URL格式不正确');
        }
    }
}