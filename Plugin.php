<?php

namespace SpamDetector;

use Mustache;
use MapasCulturais\App;
use MapasCulturais\i;
use MapasCulturais\Entities\Notification;

class Plugin extends \MapasCulturais\Plugin
{
    public function __construct($config = [])
    {
        $config += [
            'terms' => env('SPAM_DETECTOR_TERMS', [
                'citotec',
                'minecraft',
                'venda',
                'compra',
                'compre',
                'vendo',
                'vende',
                'nazismo',
                'fascismo',
                'hitler',
                'apk',
                'premium',
                'grátis',
                'gratuito',
                'download',
                'instalação',
                'instale',
                'instalar',
                'instalador',
                'instale',
                'baixar',
                'vadia',
                'puta',
                'canalha'
            ]),
            'entities' => env('SPAM_DETECTOR_ENTITIES', ['Agent', 'Opportunity', 'Project', 'Space', 'Event']),
            'fields' => env('SPAM_DETECTOR_FIELDS', ['name', 'shortDescription', 'longDescription']),
        ];

        parent::__construct($config);
    }

    public function _init()
    {
        $app = App::i();
        $plugin = $this;

        $hooks = implode('|', $plugin->config['entities']);
        // add hooks
        $app->hook("entity(<<{$hooks}>>).<<save>>:after", function () use ($plugin, $app) {   
            $roles = $this->subsiteId ? $app->repo('Role')->findBy(['subsiteId' => $this->subsiteId]) : $app->repo('Role')->findAll();
            $role_type = $this->subsiteId ? 'admin' : 'saasSuperAdmin';
            
            $user_ids = [];
            if ($roles) {
                foreach ($roles as $role) {
                    if ($role->name == $role_type) {
                        $user_ids[] = $role->userId;
                    }
                }
            }
            
            $terms = $plugin->config['terms'];
            $fields = $plugin->config['fields'];
            $spam_detector = [];
            $found_terms = [];
            
            foreach ($fields as $field) {
                if ($value = $this->$field) {
                    $lowercase_value = mb_strtolower($value);
                    foreach ($terms as $term) {
                        if (strpos($lowercase_value, mb_strtolower($term)) !== false && !in_array($term, $found_terms)) {
                            $found_terms[] = $term;
                        }
                    }

                    if ($found_terms) {
                        $spam_detector[] = [
                            'terms' => $found_terms,
                            'field' => $field,
                        ];
                    }
                }
            }
            
            if ($spam_detector) {
                foreach ($user_ids as $id) {
                    $agents = $app->repo('Agent')->findBy(['userId' => $id]);
                    foreach ($agents as $agent) {
                        if($agent->id == $id) {
                            $plugin->createNotification($agent, "Possível spam detectado. Verifique seu email.", $this, $spam_detector);
                        }
                    }
                }
            }
        });
    }
    
    public function register() {}
    
    public function createNotification($agent, $message, $entity, $spam_detections)
    {
        $app = App::i();
        $app->disableAccessControl();
        
        $notification = new Notification;
        $notification->user = $agent->user;
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
        
        $params = [
            "siteName" => $app->siteName,
            "nome" => $entity->name,
            "id" => $entity->id,
            "url" => $entity->singleUrl,
            "baseUrl" => $app->getBaseUrl(),
            "detectedDetails" => implode("\n", $detected_details),
        ];
        
        $mustache = new \Mustache_Engine();
        $content = $mustache->render($template, $params);
        
        if ($agent->emailPrivado) {
            $app->createAndSendMailMessage([
                'from' => $app->config['mailer.from'],
                'to' => $agent->emailPrivado,
                'subject' => 'Notificação de spam',
                'body' => $content,
            ]);
        }

        $app->enableAccessControl();
    }
}
