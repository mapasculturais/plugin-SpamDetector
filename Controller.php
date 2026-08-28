<?php

namespace SpamDetector;

use MapasCulturais\App;
use MapasCulturais\i;
use MapasCulturais\Controller as SpamDetectorController;

class Controller extends SpamDetectorController
{

    function __construct() {}

    public function POST_saveterms()
    {
        $app = App::i();

        $this->requireAuthentication();

        if (!$app->user->is("admin")) {
            $app->pass();
        }

        $notification = $this->sanitizeTerms($this->data['notification'] ?? null);
        $blocked = $this->sanitizeTerms($this->data['blocked'] ?? null);

        if (null === $notification || null === $blocked) {
            $this->json(['error' => i::__('invalid payload: "notification" and "blocked" must be arrays')], 400);
        }

        $data = [
            'notification' => $notification,
            'blocked' => $blocked,
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if (!Plugin::writeFileTerms($json)) {
            $this->json(['error' => i::__('unable to persist the terms file')], 500);
        }

        $this->json($data);
    }

    /**
     * Sanitizes a list of spam terms: strings only, trimmed, stripped of tags,
     * empty entries dropped, duplicates removed, array reindexed.
     * Returns null when the input is not an array (invalid payload).
     */
    protected function sanitizeTerms($terms): ?array
    {
        if (!is_array($terms)) {
            return null;
        }

        $clean = [];
        foreach ($terms as $term) {
            if (!is_string($term)) {
                continue;
            }

            $term = trim(strip_tags($term));
            if ('' === $term) {
                continue;
            }

            $clean[] = $term;
        }

        return array_values(array_unique($clean));
    }
}
