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
            'terms' => env('SPAM_DETECTOR_TERMS', ['citotec', 'minecraft']),
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
        $app->hook("entity(<<{$hooks}>>).<<save>>:after", function() use ($plugin, $app) {
            $admins = ['saasSuperAdmin','admin'];
            $user_ids = [];
            $roles = $app->repo('Role')->findAll();

            if ($roles) {
                foreach ($roles as $role) {
                    if (in_array($role->name, $admins)) {
                        $user_ids[] = $role->userId;
                    }
                }
            }
            
            $terms = $plugin->config['terms'];
            $fields = $plugin->config['fields'];
            
            $spamDetector = [];
            
            foreach ($fields as $field) {
                $foundTerms = [];
                if ($value = $this->$field) {
                    $lowercaseValue = mb_strtolower($value);
                    foreach ($terms as $term) {
                        if (strpos($lowercaseValue, mb_strtolower($term)) !== false && !in_array($term, $foundTerms)) {
                            $foundTerms[] = $term;
                        }
                    }
                    
                    if (!empty($foundTerms)) {
                        $spamDetector[] = [
                            'terms' => $foundTerms,
                            'field' => $field,
                        ];
                    }
                }
            }

            if (!empty($spamDetector)) {
                foreach ($user_ids as $id) {
                    $agents = $app->repo('Agent')->findBy(['userId' => $id]);
                    foreach ($agents as $agent) {
                        $plugin->createNotification($agent, "Possível spam detectado. Verifique seu email.", $this, $spamDetector);
                    } 
                }
                
            }
        });
    }

    public function register()
    {
    }
    
    public function createNotification($agent, $message, $entity, $spamDetections)
    {
        $app = App::i();
        $app->disableAccessControl();
        
        $notification = new Notification;
        $notification->user = $agent->user;
        $notification->message = $message;
        $notification->save(true);
        
        $filename = $app->view->resolveFilename("views/emails", "email-spam.html");       
        $template = file_get_contents($filename);
        
        $fieldTranslations = [
            "name" => i::__("Nome"),
            "shortDescription" => i::__("Descrição Curta"),
            "longDescription" => i::__("Descrição Longa"),
        ];

        $detectedDetails = [];
        foreach ($spamDetections as $detection) {
            $translatedField = isset($fieldTranslations[$detection['field']]) ? $fieldTranslations[$detection['field']] : $detection['field'];
            $detectedDetails[] = "Campo: $translatedField, Termos: " . implode(', ', $detection['terms']) . '<br>';
        }
        
        $params = [
            "siteName" => $app->siteName,
            "nome" => $entity->name,
            "id" => $entity->id,
            "url" => $entity->singleUrl,
            "baseUrl" => $app->getBaseUrl(),
            "detectedDetails" => implode("\n", $detectedDetails), 
        ];

        $mustache = new \Mustache_Engine();
        $content = $mustache->render($template,$params);
        
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