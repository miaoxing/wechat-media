<?php

namespace MiaoxingTest\WechatMedia\Service;

use Miaoxing\WechatMedia\Service\WechatMedia;

class WechatMediaTest extends \Miaoxing\Plugin\Test\BaseTestCase
{
    public function testGetTypeKey()
    {
        $type = wei()->wechatMedia->getTypeKey(WechatMedia::TYPE_ARTICLE);
        $this->assertEquals('article', $type);

        $type = wei()->wechatMedia->getTypeKey(WechatMedia::TYPE_TEXT);
        $this->assertEquals('text', $type);

        $type = wei()->wechatMedia->getTypeKey(WechatMedia::TYPE_IMAGE);
        $this->assertEquals('image', $type);
    }

    public function testGenerateTextApiData()
    {
        $ret = wei()->wechatMedia->generateApiData([
            'type' => WechatMedia::TYPE_TEXT,
            'content' => 'text',
        ]);

        $this->assertEquals('text', $ret['data']['text']['content']);
    }
}
