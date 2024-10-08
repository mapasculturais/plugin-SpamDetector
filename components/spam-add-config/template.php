<?php

/**
 * @var MapasCulturais\App $app
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 */

use MapasCulturais\i;

$this->import('
    mc-tag-list
');

?>
<div id="spam-add-config">
    <h3> <?= i::__('Configuração de Tags para Notificação e Bloqueio')?></h3>

    <div>
        <label> <?= i::__('Adicione os textos para serem notificados')?></label>
        <input type="text" placeholder="Digite uma nova tag de notificação" @keydown="change($event, 'notificationTags')"  @blur="clear($event)">
        <mc-tag-list :tags="notificationTags" @remove="saveTags()" editable></mc-tag-list>
    </div>

    <div>
        <label> <?= i::__('Adicione os textos para serem bloqueados')?></label>
        <input type="text" placeholder="Digite uma nova tag de bloqueio" @keydown="change($event, 'blockedTags')" @blur="clear($event)">
        <mc-tag-list :tags="blockedTags"  @remove="saveTags()" editable></mc-tag-list>
    </div>
</div>