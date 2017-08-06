<?php

namespace Miaoxing\WechatMedia\Controller\Admin;

use miaoxing\plugin\BaseController;

class WechatMedias extends BaseController
{
    public function uploadImgAction($req)
    {
        $upload = wei()->upload;
        $result = $upload();
        if (!$result) {
            return $this->err($upload->getFirstMessage('上传文件'));
        }

        $file = $upload->getFile();
        $account = wei()->wechatAccount->getCurrentAccount();
        $api = $account->createApiService();

        $http = $api->uploadImg($file);
        if (!$http) {
            return $api->getResult();
        }

        return $this->suc([
            'url' => $http['url']
        ]);
    }
}
