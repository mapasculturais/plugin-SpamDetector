<?php
/**
 * @var MapasCulturais\App $app
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 */
use MapasCulturais\i;

$this->import('
    mc-alert
');
?>

<div class="spam-warning">
    <mc-alert type="warning" class="spam-warning">
        <label> 
            <?= i::__('Um termo de spam foi detectado.') ?>
            <input type="checkbox" v-model="spamStatus" @change="setSpamStatus"/><?= i::__('Classificar como Spam') ?>
        </label>
    </mc-alert>
</div>