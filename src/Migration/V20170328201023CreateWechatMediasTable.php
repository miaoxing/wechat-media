<?php

namespace Miaoxing\WechatMedia\Migration;

use Miaoxing\Plugin\BaseMigration;

class V20170328201023CreateWechatMediasTable extends BaseMigration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $this->schema->table('wechatMedias')
            ->id()
            ->int('appId')
            ->string('wechatMediaId', 128)
            ->tinyInt('type', 1)
            ->tinyInt('temp', 1)->defaults(1)
            ->string('path', 255)
            ->string('wechatUrl', 255)
            ->timestampsV1()
            ->userstampsV1()
            ->exec();
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        $this->schema->dropIfExists('wechatMedias');
    }
}
