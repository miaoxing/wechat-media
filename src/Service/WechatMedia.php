<?php

namespace Miaoxing\WechatMedia\Service;

use Miaoxing\Article\Service\Article;

/**
 * @property \Wei\Logger $logger
 */
class WechatMedia extends \miaoxing\plugin\BaseModel
{
    const TYPE_ARTICLE = 10;

    const TYPE_TEXT = 1;

    const TYPE_IMAGE = 2;

    const TYPE_AUDIO = 3;

    const TYPE_VIDEO = 15;

    const TYPE_COMMODITY = 11; // 商品消息

    const TYPE_CARD = 16;

    protected $typeNames = [
        10 => '图文',
        1 => '文本',
        2 => '图片',
    ];

    protected $typeKeys = [
        10 => 'article',
        1 => 'text',
        2 => 'image',
    ];

    /**
     * 临时素材的过期时间是3天
     *
     * @var int
     */
    protected $expire = 259200;

    protected $table = 'wechatMedias';

    protected $providers = [
        'db' => 'app.db',
    ];

    /**
     * @var string
     */
    protected $cachePrefix = 'wechatMedia';

    public function getTypeKey($type)
    {
        return isset($this->typeKeys[$type]) ? $this->typeKeys[$type] : false;
    }

    /**
     * Record: 根据资源路径从缓存中查找或初始化资源,即findOrInitByPathFromCache
     *
     * @param $path
     * @return $this
     */
    public function findPath($path)
    {
        return $this->cache()->setCacheKey($this->cachePrefix . $path)->findOrInit(['path' => $path]);
    }

    /**
     * Repo: 生成供微信消息接口调用的数据
     *
     * @param array|object $data
     * @return array
     */
    public function generateApiData($data)
    {
        if (!isset($this->typeKeys[$data['type']])) {
            return ['code' => -1, 'message' => '不支持该消息类型'];
        }

        $key = $this->typeKeys[$data['type']];
        $method = 'generate' . ucfirst($key) . 'ApiData';

        return $this->$method($data);
    }

    /**
     * Record: 判断当前素材是否要上传到微信服务器
     */
    public function shouldUpload()
    {
        // 新记录或还未上传
        if ($this->isNew || !$this['wechatMediaId']) {
            return true;
        }

        // 永久素材不用重新上传
        if (!$this['temp']) {
            return false;
        }

        // 临时素材离过期只有1小时(群发需要一定时间)
        if (time() - strtotime($this['updateTime']) > ($this->expire - 3600)) {
            return true;
        }

        return false;
    }

    /**
     * 调用预览接口并记录最后预览的用户名称
     *
     * @param array $data
     * @param string $wechatUsername
     * @return array
     */
    public function preview($data, $wechatUsername)
    {
        $api = wei()->wechatAccount->getCurrentAccount()->createApiService();
        $api->previewMassMessage(['towxname' => $wechatUsername] + $data);

        return $api->getResult();
    }

    /**
     * @param array $data
     * @return array
     */
    protected function generateTextApiData($data)
    {
        return [
            'code' => 1,
            'message' => '操作成功',
            'mediaId' => '',
            'data' => [
                'text' => [
                    'content' => $data['content'],
                ],
                'msgtype' => 'text',
            ],
        ];
    }

    /**
     * @param array $data
     * @return array
     */
    protected function generateArticleApiData($data)
    {
        // 如果已经上传过,直接返回
        if ($data['mediaId']) {
            return $this->createArticleRet($data['mediaId']);
        }

        $articles = wei()->article()->findByIds($data['articleIds']);
        if (!$articles->length()) {
            return ['code' => -1, 'message' => '图文消息不能为空'];
        }

        return $this->generateByArticles($articles);
    }

    /**
     * 上传图文,生成供微信消息接口调用的数据
     *
     * @param Article|Article[] $articles
     * @param bool $saveMedia 是否保存图文素材id到数据库
     * @return array
     */
    public function generateByArticles(Article $articles, $saveMedia = true)
    {
        $wxArticles = [];
        $api = wei()->wechatAccount->getCurrentAccount()->createApiService();
        foreach ($articles as $article) {
            // 1. 上传缩略图
            $media = wei()->wechatMedia()->findPath($article['thumb']);
            if ($media->shouldUpload()) {
                $http = $api->uploadMediaFromUrl($article['thumb']);
                if (!$http) {
                    return $api->getResult();
                }
                // 缩略图返回的是thumb_media_id,图片返回的是media_id
                $mediaId = isset($http['thumb_media_id']) ? $http['thumb_media_id'] : $http['media_id'];
                $media->setAppId()->save([
                    'temp' => true,
                    'type' => static::TYPE_IMAGE,
                    'wechatMediaId' => (string) $mediaId,
                ]);
            }

            // 2. 上传图文中的图片
            $ret = $article->replaceImages([$this, 'updateUrlToWechatUrl']);
            if ($ret['code'] !== 1) {
                return $ret;
            }

            $ext = [
                'thumb_media_id' => $media['wechatMediaId'],
                'author' => $article['author'],
                'title' => $article['title'],
                'content' => $article['content'],
                'digest' => $article['intro'],
                'show_cover_pic' => $article['showCoverPic'],
            ];

            if ($sourceUrl = $article->getSourceUrl()) {
                $ext += ['content_source_url' => $sourceUrl];
            }

            $wxArticles[] = $ext;
        }

        // 3. 上传图文
        $path = $this->getPathFromArticles($articles);
        $media = wei()->wechatMedia()->findPath($path);
        if ($media->shouldUpload()) {
            $http = $api->uploadNews(['articles' => $wxArticles]);
            if (!$http) {
                return $api->getResult();
            }
            $media->setAppId()->fromArray([
                'temp' => false,
                'type' => static::TYPE_ARTICLE,
                'wechatMediaId' => $http['media_id'],
            ]);
            if ($saveMedia) {
                $media->save();
            }
        }

        return $this->createArticleRet($media['wechatMediaId']);
    }

    /**
     * 如果数据库没有图片的微信地址,上传到微信并记录到数据库
     *
     * @param string $url
     * @return string
     * @throws
     */
    public function updateUrlToWechatUrl($url)
    {
        $media = wei()->wechatMedia()->findPath($url);
        if (!$media['wechatUrl']) {
            $api = wei()->wechatAccount->getCurrentAccount()->createApiService();
            $http = $api->uploadImgFromUrl($url);
            if (!$http) {
                // 抛出异常供replaceImages捕获
                throw new \Exception($api->getMessage(), $api->getCode());
            }
            $media->setAppId()->save([
                'type' => static::TYPE_IMAGE,
                'wechatUrl' => $http['url'],
            ]);
        }

        return $media['wechatUrl'];
    }

    /**
     * @param string $url
     * @return array
     * @todo 迁移updateUrlToWechatUrl
     */
    public function updateUrlToWechatUrlRet($url)
    {
        try {
            return $this->suc(['url' => $this->updateUrlToWechatUrl($url)]);
        } catch (\Exception $e) {
            return $this->err($e->getMessage(), $e->getCode());
        }
    }

    /**
     * 生成资源的图文路径
     *
     * @param array $articles
     * @return string
     */
    protected function getPathFromArticles($articles)
    {
        $keys = [];
        foreach ($articles as $article) {
            $keys[] = $article['id'] . '_' . ($article['updateTime'] ?: date('Y-m-d H:i:s'));
        }

        return implode(',', $keys);
    }

    /**
     * 生成图文的群发数据内容
     *
     * @param string $mediaId
     * @return array
     */
    protected function createArticleRet($mediaId)
    {
        return [
            'code' => 1,
            'message' => '操作成功',
            'mediaId' => $mediaId,
            'data' => [
                'mpnews' => [
                    'media_id' => $mediaId,
                ],
                'msgtype' => 'mpnews',
            ],
        ];
    }

    public function afterSave()
    {
        parent::afterSave();
        $this->cache->remove($this->cachePrefix . $this['path']);
    }

    public function afterDestroy()
    {
        parent::afterDestroy();
        $this->cache->remove($this->cachePrefix . $this['path']);
    }
}
