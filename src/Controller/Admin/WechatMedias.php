<?php

namespace Miaoxing\WechatMedia\Controller\Admin;

use miaoxing\plugin\BaseController;

class WechatMedias extends BaseController
{
    /**
     * 将外部图片地址映射到微信图片地址
     */
    public function createAction($req)
    {
        $wechatUrl = wei()->wechatMedia->updateUrlToWechatUrl($req['url']);

        return $this->suc([
            'url' => $wechatUrl,
        ]);
    }
}
