<?php

namespace SpamDetector;

use DateTime;
use Mustache;
use MapasCulturais\i;
use MapasCulturais\App;
use MapasCulturais\Entities\Agent;
use MapasCulturais\Entities\Event;
use MapasCulturais\Entities\Space;
use MapasCulturais\Entities\Project;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\Entities\Notification;
use MapasCulturais\Entity;

class Plugin extends \MapasCulturais\Plugin
{
    protected static $instance;

    public function __construct($config = [])
    {
        $default_terms = [
            'minecraft',
            'venda',
            'compra',
            'compre',
            'vendo',
            'vende',
            'nazismo',
            'fascismo',
            'hitler',
            'premium',
            'grátis',
            'gratuito',
            'download',
            'baixar',
            'vadia',
            'puta',
            'canalha',
            'farmácia',
            'farma',
        ];

        $terms_block = [
            'citotec',
            'c1totec',
            'cytotec',
            'sytotec',
            'sitotec',
            's1totec',
            'apk',
            'install',
            'installer',
            'instale',
            'instalar',
            'instalador',
            'mysoprosotol',
            'mysoprosotol',
            'myzoprozotol',
            'mysoprozotol',
            'myzoprosotol',
            'misoprosotol',
            'misoprosotol',
            'mizoprozotol',
            'misoprozotol',
            'mizoprosotol'
        ];

        if(isset($config['termsBlock'])) {
            $terms_block = $terms_block += $config['termsBlock'];
            $config['termsBlock'] = $terms_block;
        }
        
        if(isset($config['terms'])) {
            $default_terms = $default_terms += $config['terms'];
            $config['terms'] = $default_terms;
        }

        $default_fields = [
            'name', 
            'shortDescription', 
            'longDescription', 
            'nomeSocial', 
            'nomeCompleto', 
            'comunidadesTradicionalOutros',
            'facebook',
            'twitter',
            'instagram',
            'linkedin',
            'vimeo',
            'spotify',
            'youtube',
            'pinterest',
            'tiktok'
        ];

        $config += [
            'terms' => env('SPAM_DETECTOR_TERMS', $default_terms),
            'entities' => env('SPAM_DETECTOR_ENTITIES', ['Agent', 'Opportunity', 'Project', 'Space', 'Event']),
            'fields' => env('SPAM_DETECTOR_FIELDS', $default_fields),
            'termsBlock' => env('SPAM_DETECTOR_TERMS_BLOCK', $terms_block)
        ];

        parent::__construct($config);
        self::$instance = $this;
    }

    public function _init()
    {
        $app = App::i();
        $plugin = $this;

        $hooks = implode('|', $plugin->config['entities']);
        $last_spam_sent = null;

        $app->hook("entity(<<{$hooks}>>).save:before", function () use ($plugin, $app) {
            /** @var Entity $this */
            if($plugin->getSpamTerms($this, $plugin->config['termsBlock'])) {
                $this->spamBlock = true;
                $this->setStatus(-10);
            }
        });

        // Verifica se existem termos maliciosos e dispara o e-mail e a notificação
        $app->hook("entity(<<{$hooks}>>).save:after", function () use ($plugin, $last_spam_sent) {
            /** @var Entity $this */

            $users = $plugin->getAdminUsers($this);
            $terms = array_merge($plugin->config['termsBlock'], $plugin->config['terms']);

            $spam_terms = $plugin->getSpamTerms($this, $terms);
            $current_date_time = new DateTime();
            $current_timestamp = $current_date_time->getTimestamp();
            $eligible_spam = $last_spam_sent ?? $this->spam_sent_email;

            $is_spam_eligible = !$eligible_spam || ($current_timestamp - $eligible_spam->getTimestamp()) >= 86400;
            $is_spam_status_valid = !$this->spam_status;

            if ($spam_terms && $is_spam_eligible && $is_spam_status_valid) {
                $ip = $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';

                foreach ($users as $user) {
                    $plugin->createNotification($user->profile, $this, $spam_terms, $ip);
                }

                $dict_entity = $plugin->dictEntity($this, 'artigo');
                $message = i::__("{$dict_entity} {$this->name} foi enviado para moderação. Informamos que registramos seu ip: {$ip}");
                $notification = new Notification;
                $notification->user = $this->ownerUser;
                $notification->message = $message;
                $notification->save(true);
            }
        });

        // Garante que o termo encontrado fique salvo e o e-mail seja disparado
        $app->hook("entity(<<{$hooks}>>).save:finish", function () use ($plugin, $app) {
            /** @var Entity $this */
            if($plugin->getSpamTerms($this, $plugin->config['termsBlock'])) {
                $this->ownerUser->setStatus(-10);
            }
        });

        // remove a permissão de publicar caso encontre termos que estão na lista de termos elegível a bloqueio
        $app->hook("entity(<<{$hooks}>>).canUser(publish)", function ($user, &$result) use($plugin, &$last_spam_sent) {
            /** @var Entity $this */
            if($plugin->getSpamTerms($this, $plugin->config['termsBlock']) && !$user->is('admin')) {
                $result = false;
            }
        });

        // Caso for encontrado o termo e o usuário logado for o admin, irá aparecer na entidade um warning
        $app->hook("template(<<{$hooks}>>.<<edit|single>>.entity-header):before", function() use($plugin, $app) {
            $entity = $this->controller->requestedEntity;

            if($plugin->getSpamTerms($entity, $plugin->config['terms']) && $app->user->is('admin')) {
                $this->part('admin-spam-warning');
                $app->view->enqueueStyle('app-v2', 'admin-spam-warning', 'css/admin-spam-warning.css');
            }
        });
    }
    
    public function register() {
        $entities = $this->config['entities'];

        foreach($entities as $entity) {
            $namespace = "MapasCulturais\\Entities\\{$entity}";

            $this->registerMetadata($namespace,'spam_sent_email', [
                'label' => i::__('Data de envio do e-mail'),
                'type' => 'DateTime',
                'default' => null,
            ]);
            
            $this->registerMetadata($namespace,'spam_status', [
                'label' => i::__('Classificar como Spam'),
                'type' => 'boolean',
                'default' => false,
            ]);
        }
    }
    
    public function createNotification($recipient, $entity, $spam_detections, $ip)
    {
        $app = App::i();
        $app->disableAccessControl();
        
        $is_save = !$entity->spamBlock;
        $message = $this->getNotificationMessage($entity, $is_save);
        $notification = new Notification;
        $notification->user = $recipient->user;
        $notification->message = $message;
        $notification->save(true);
        
        $filename = $app->view->resolveFilename("views/emails", "email-spam.html");
        $template = file_get_contents($filename);
        
        $field_translations = [
            "name" => i::__("Nome"),
            "shortDescription" => i::__("Descrição Curta"),
            "longDescription" => i::__("Descrição Longa"),
        ];
        
        $detected_details = [];
        foreach ($spam_detections as $detection) {
            $translated_field = isset($field_translations[$detection['field']]) ? $field_translations[$detection['field']] : $detection['field'];
            $detected_details[] = "Campo: $translated_field, Termos: " . implode(', ', $detection['terms']) . '<br>';
        }

        $dict_entity = $this->dictEntity($entity, 'artigo');

        $mail_notification_message = i::__('O sistema detectou possível spam em um conteúdo recente. Por favor, revise as informações abaixo e tome as medidas necessárias:');
        $mail_blocked_message = i::__("O sistema detectou um conteúdo inadequado neste cadastro e moveu-o para a lixeira. Seguem abaixo os dados para análise do conteúdo:");
        $mail_message = $is_save ? $mail_notification_message : $mail_blocked_message;

        $params = [
            "siteName" => $app->siteName,
            "nome" => $entity->name,
            "id" => $entity->id,
            "url" => $entity->singleUrl,
            "baseUrl" => $app->getBaseUrl(),
            "detectedDetails" => implode("\n", $detected_details),
            "ip" => $ip,
            "adminName" => $recipient->name,
            'mailMessage' => $mail_message,
            'dictEntity' => $this->dictEntity($entity, 'none')
        ];
        
        $mustache = new \Mustache_Engine();
        $content = $mustache->render($template, $params);

        if ($email = $this->getAdminEmail($recipient)) {
            $app->createAndSendMailMessage([
                'from' => $app->config['mailer.from'],
                'to' => $email,
                'subject' => $is_save ? i::__("Spam - Conteúdo suspeito") : i::__("Spam - {$dict_entity} foi bloqueado(a)"),
                'body' => $content,
            ]);
        }

        // Salvar metadado
        $date_time = new DateTime();
        $date_time->add(new \DateInterval('PT10S'));
        $date_time = $date_time->format('Y-m-d H:i:s');
        
        $conn = $app->em->getConnection();
        if(!$conn->fetchAll("SELECT * FROM agent_meta WHERE key = 'spam_sent_email' and object_id = {$entity->id}")) {
            $conn->executeQuery("INSERT INTO agent_meta (id, object_id, key, value) VALUES (nextval('agent_meta_id_seq'), {$entity->id}, 'spam_sent_email', '{$date_time}')");
        } else {
            $conn->executeQuery("UPDATE agent_meta SET value = '{$date_time}' WHERE object_id = {$entity->id} AND key = 'spam_sent_email'");
        }

        $app->enableAccessControl();
    }   

    /**
     *  Retorna o texto relacionado a entidade
     * @param Entity $entity 
     * @return string 
     */
    public function dictEntity(Entity $entity, $type = "preposição"): string
    {
        $class = $entity->getClassName();

        switch ($type) {
            case 'preposição':
                $prefixes = (object) ["f" => "na", "m" => "no"];
                break;
            case 'pronome':
                $prefixes = (object) ["f" => "esta", "m" => "este"];
                break;
            case 'artigo':
                $prefixes = (object) ["f" => "a", "m" => "o"];
                break;
            case 'none':
                $prefixes = (object) ["f" => "", "m" => ""];
                break;
            default:
                $prefixes = (object) ["f" => "", "m" => ""];
                break;
        }

        $entities = [
            Agent::class => "{$prefixes->m} Agente",
            Opportunity::class => "{$prefixes->f} Oportunidade",
            Project::class => "{$prefixes->m} Projeto",
            Space::class => "{$prefixes->m} Espaço",
            Event::class => "{$prefixes->m} Evento",
        ];

        return $entities[$class];
    }

    /**
     *  Retorna o texto com o nome da tabela
     * @param Entity $entity 
     * @return string 
     */
    public function dictTable(Entity $entity): string
    {
        $class = $entity->getClassName();

        $entities = [
            Agent::class => "agent",
            Opportunity::class => "opportunity",
            Project::class => "project",
            Space::class => "space",
            Event::class => "event",
        ];

        return $entities[$class];
    }

    /**
     * @param string $text
     * @return string
     */
    public function formatText($text)
    {
        $text = trim($text);
        $text = strip_tags($text);
        $text = mb_strtolower($text);

        return $text;
    }

    /**
     * @param object $entity Objeto da entidade que deve ter a propriedade `subsiteId`. A presença desta propriedade determina o tipo de papéis a serem recuperados.
     * 
     * @return array Um array contendo os IDs dos usuários que têm um papel administrativo. O array pode estar vazio se nenhum papel administrativo for encontrado.
    */
    public function getAdminUsers($entity): array {
        $app = App::i();

        $roles = $app->repo('Role')->findBy(['subsiteId' => [$entity->subsiteId, null]]);
        
        $users = [];
        if ($roles) {
            foreach ($roles as $role) {
                if ($role->user->is('admin')) {
                    $users[] = $role->user;
                }
            }
        }

        return $users;
    }

    /**
     * @param object $entity Objeto da entidade a ser validada. A entidade deve ter propriedades que correspondem aos campos configurados.
     * 
     * @return array Retorna um array contendo os campos onde termos de spam foram encontrados.
    */
    public function getSpamTerms($entity, $terms): array {
        $fields = $this->config['fields'];
        $spam_detector = [];
        $found_terms = [];
        $special_chars = ['@', '#', '$', '%', '^', '·', '&', '*', '(', ')', '-', '_', '=', '+', '{', '}', '[', ']', '|', ':', ';', '"', '\'', '<', '>', ',', '.', '?', '/', ' '];
        $special_chars = array_map(fn($char) => preg_quote($char, '/'), $special_chars);
        $special_chars = '[' . implode('', $special_chars) . ']*';

        foreach ($fields as $field) {
            if ($value = $entity->$field) {
                $lowercase_value = $this->formatText($value);
                
                foreach ($terms as $term) {
                    $lowercase_term = $this->formatText($term);
                    $_term = implode("{$special_chars}", mb_str_split($lowercase_term));

                    $pattern = '/([^\w]|[_0-9]|^)' . $_term . '([^\w]|[_0-9]|$)/';
                    
                    if (preg_match($pattern, $lowercase_value) && !in_array($term, $found_terms)) {
                        $found_terms[$field][] = $term;
                    }
                }
            }
        }

        if ($found_terms) {
            foreach($found_terms as $key => $value) {
                $spam_detector[] = [
                    'field' => $key,
                    'terms' => $value
                ];
            }
        }

        return $spam_detector;
    }

    /**
     * @param object $entity Objeto da entidade que contém as propriedades `name` e `singleUrl`. A propriedade `name` é usada para identificar a entidade na mensagem, e `singleUrl` é o link para a verificação.
     * @param bool $is_save Indica o status de salvamento da entidade.
     * 
     * @return string Retorna uma mensagem formatada de notificação baseada no status de salvamento.
    */
    public function getNotificationMessage($entity, $is_save): string {
        $dict_entity = $this->dictEntity($entity, 'artigo');
        $message_save = i::__("Possível spam detectado {$dict_entity} - <strong><i>{$entity->name}</i></strong><br><br> <a href='{$entity->singleUrl}'>Clique aqui</a> para verificar. Mais detalhes foram enviados para o seu e-mail");
        $message_insert = $message_insert = i::__("Possível spam detectado {$dict_entity} - <strong><i>{$entity->name}</i></strong><br><br> Apenas um administrador pode publicar este conteúdo, <a href='{$entity->singleUrl}'>clique aqui</a> para verificar. Mais detalhes foram enviados para o seu e-mail");

        $message = $is_save ? $message_save : $message_insert;

        return $message;
    }
    
    /**
     * @param object $agent Objeto que representa o agente. O objeto deve ter as propriedades `emailPrivado`, `emailPublico`, e `user` (que deve ter a propriedade `email`).
     * 
     * @return string O endereço de e-mail do agente.
    */
    public function getAdminEmail($recipient): string {
        if($recipient->emailPrivado) {
            $email = $recipient->emailPrivado;
        } else if($recipient->emailPublico) {
            $email = $recipient->emailPublico;
        } else {
            $email = $recipient->user->email;
        }

        return $email;
    }

    public static function getInstance(){
        return self::$instance;
    }
}
