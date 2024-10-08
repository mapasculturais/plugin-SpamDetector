<?php

/**
 * @var MapasCulturais\App $app
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 */

use MapasCulturais\i;

$this->import("
    mc-icon
    mc-modal
    spam-add-config
");
?>
<li>
    <mc-modal button-label="<?php i::_e('Controle de Spam') ?>">
        <template #button='{close, open, toogle, loading}'>
            <a href="#" @click="open()">
                <mc-icon name="security"> </mc-icon>
                <?= i::__('Controle de SPAM') ?>
            </a>
        </template>

        <template v-if="entity?.id && entity.status==1" #actions="modal">
            <spam-add-config></spam-add-config>
        </template>
    </mc-modal>
</li>