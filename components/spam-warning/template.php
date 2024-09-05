<?php
/**
 * @var MapasCulturais\App $app
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 */
use MapasCulturais\i;
use SpamDetector\Plugin;

$instance = Plugin::getInstance();
$entity = $this->controller->requestedEntity;
$dict_entity = $instance->dictEntity($entity, 'pronome');
$dict_entity = ucfirst($dict_entity);

$this->import('
    mc-alert
    mc-confirm-button
');
?>

<div class="spam-alert">
    <mc-alert v-if="!entity.spam_status" type="danger" class="spam-alert">
        <label> 
            <?= i::__('Identificamos um possível spam neste cadastro. CASO NÃO SEJA,')  ?>
                
            <mc-confirm-button message="<?= i::esc_attr__('Deseja retirar do spam?')?>" @confirm="setSpamStatus()">
                <template #button="modal">
                    <button @click="modal.open()" class="spam-click">
                        <?php i::_e("clique aqui") ?>
                    </button>
                </template>
            </mc-confirm-button> 

            <?= i::__('para desativar mensagens futuras. CASO SEJA SPAM, clique no botão <strong>Excluir</strong> no rodapé.')  ?>
        </label>
    </mc-alert>

    <mc-alert v-else type="warning" class="spam-alert">
        <label> 
            <?= i::__("{$dict_entity} já foi detectada como possível spam. Caso deseje reativar as notificações,") ?>

            <mc-confirm-button message="<?= i::esc_attr__('Deseja marcar como spam?')?>" @confirm="setSpamStatus()">
                <template #button="modal">
                    <button @click="modal.open()" class="spam-click">
                        <?= i::__('clique aqui') ?>
                    </button>
                </template>
            </mc-confirm-button> 
        </label>
    </mc-alert>
</div>