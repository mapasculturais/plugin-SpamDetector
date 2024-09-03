# plugin-SpamDetector
> Plugin de detecção de spam para uso na plataforma Mapas Culturais

## Efeitos do uso do plugin

- O plugin implementa na plataforma um sistema de detecção automática de spam, identificando termos suspeitos em entidades como Agente, Oportunidade, Projeto, Espaço e Evento, ajudando a manter o conteúdo da plataforma seguro e livre de spam.

## Requisitos Mínimos

- Mapas Culturais v7.0.0^

## Configuração básica

### Configurações necessárias para uso no ambiente de desenvolvimento ou produção

- No arquivo `docker/common/config.d/plugins.php`, adicione a linha `'SpamDetector'` para ativar o plugin:

    ```php
    <?php

    return [
        'plugins' => [
            'MultipleLocalAuth' => [ 'namespace' => 'MultipleLocalAuth' ],
            'SamplePlugin' => ['namespace' => 'SamplePlugin'],
            "SpamDetector",
        ]
    ];
    ```

## Como funciona

- O plugin monitora atualizações e criações de entidades específicas (Agente, Oportunidade, Projeto, Espaço, Evento) e verifica se os campos configurados (por padrão, `name`, `shortDescription`, `longDescription`) contêm termos suspeitos predefinidos, como "citotec" e "minecraft".
- Se termos suspeitos forem encontrados, o plugin notifica automaticamente os administradores (super admins e admins) via e-mail, listando os termos detectados e os campos onde foram encontrados.

## Personalização

- As configurações do plugin permitem personalizar os termos a serem detectados (`terms`) e bloqueados (`termsBlock`), as entidades monitoradas (`entities`), e os campos onde a detecção deve ocorrer (`fields`). Essas configurações podem ser definidas dentro de uma chave chamada `config` no arquivo `docker/common/config.d/plugins.php`, como mostrado abaixo:

    ```php
    <?php

    return [
        'plugins' => [
            'MultipleLocalAuth' => [ 'namespace' => 'MultipleLocalAuth' ],
            'SamplePlugin' => ['namespace' => 'SamplePlugin'],
            "SpamDetector" => [
                "namespace" => "SpamDetector",
                "config" => [
                    // suas configurações personalizadas abaixo, por exemplo:
                    "terms" => ['compra', 'minecraft', 'venda', 'download'],
                    "termsBlock" => ['citotec', 'apk']
                ]
            ]
        ]
    ];
    ```

- **IMPORTANTE:** Ao adicionar configurações personalizadas na chave `config`, o que for adicionado irá **sobrescrever** a configuração padrão. Certifique-se de incluir todos os parâmetros necessários para evitar comportamentos indesejados.

## Notificações

- Quando um possível spam é detectado, uma notificação é enviada aos administradores cadastrados, e um e-mail é gerado usando um template Mustache (`email-spam.html`). O e-mail contém detalhes sobre os termos e os campos onde foram encontrados, juntamente com um link para a entidade suspeita na plataforma.

