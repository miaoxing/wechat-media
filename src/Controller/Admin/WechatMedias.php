<?php

namespace Miaoxing\WechatMedia\Controller\Admin;

use Miaoxing\Plugin\BaseController;
use Wei\Request;

class WechatMedias extends BaseController
{
    /**
     * 将外部图片地址映射到微信图片地址
     */
    public function createAction(Request $req)
    {
        $validator = wei()->validate([
            // 如果传 $req 会导致调用 getUrl 而一直有值
            'data' => $req->getParameterReference('post'),
            'rules' => [
                'url' => [],
            ],
            'names' => [
                'url' => 'Url',
            ],
        ]);
        if (!$validator->isValid()) {
            return $this->err($validator->getFirstMessage());
        }

        $ret = wei()->wechatMedia->updateUrlToWechatUrlRet($req['url']);

        return $ret;
    }
}
