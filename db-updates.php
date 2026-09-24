<?php

/**
 * Correções de metadados do SpamDetector.
 *
 * Critério (independente das listas de termos de cada ambiente):
 * - Detecção real sempre grava spam_sent_email em createNotification().
 * - spam_status sem spam_sent_email veio do default=1 persistido em saves comuns
 *   (falso positivo) e deve ser removido.
 *
 * Não reavalia conteúdo contra termos: as listas diferem entre instalações.
 */

use MapasCulturais\App;
use function MapasCulturais\__table_exists;

return [
    'SpamDetector: remove spam_status sem detecção real (sem spam_sent_email)' => function () {
        $app = App::i();
        $conn = $app->em->getConnection();

        // Entidades monitoradas pelo plugin (tabelas *_meta)
        $entities = ['agent', 'opportunity', 'project', 'space', 'event'];

        $total_removed = 0;

        foreach ($entities as $entity) {
            $table_meta = "{$entity}_meta";

            if (!__table_exists($table_meta)) {
                continue;
            }

            // Remove apenas status órfão: nunca houve e-mail/notificação de spam
            $sql = "DELETE FROM {$table_meta} s
                    WHERE s.key = 'spam_status'
                      AND NOT EXISTS (
                          SELECT 1
                          FROM {$table_meta} e
                          WHERE e.object_id = s.object_id
                            AND e.key = 'spam_sent_email'
                      )";

            // DBAL 2: executeUpdate / DBAL 3: executeStatement
            if (method_exists($conn, 'executeStatement')) {
                $removed = (int) $conn->executeStatement($sql);
            } else {
                $removed = (int) $conn->executeUpdate($sql);
            }

            $remaining_sql = "SELECT COUNT(*) FROM {$table_meta} WHERE key = 'spam_status'";
            if (method_exists($conn, 'fetchOne')) {
                $remaining = (int) $conn->fetchOne($remaining_sql);
            } else {
                $remaining = (int) $conn->fetchColumn($remaining_sql);
            }

            $total_removed += $removed;

            $app->log->debug("SpamDetector db-update: {$table_meta}: removidos {$removed} spam_status órfãos (restam {$remaining} com detecção real)");
        }

        $app->log->debug("SpamDetector db-update: total removido: {$total_removed}");
    },
];
