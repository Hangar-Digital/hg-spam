# HG Spam

Plugin WordPress que filtra spam nos formulários do **Contact Form 7** usando um modelo de decisão (Ollama ou OpenRouter), em vez de listas de palavras ou CAPTCHA.

## Para que serve

A cada envio de formulário, o plugin manda os campos preenchidos ao modelo com uma pergunta ("essa mensagem é spam?"). O modelo devolve uma probabilidade de 0 a 1. Se ela for maior ou igual ao limite configurado, o CF7 trata o envio como spam e não dispara o e-mail.

A instrução enviada ao modelo é editável no painel, então dá para descrever o negócio do site (ex.: "sou empresa que vende produtos alimentícios para a indústria") e o que conta como spam para ele.

## Requisitos

- PHP 8.1 ou superior
- WordPress com o plugin **Contact Form 7** ativo
- Um servidor de decisão:
  - **Ollama** (local ou próprio), ou
  - **OpenRouter** (precisa de API key)

## Instalação

1. Copie a pasta `hg-spam/` para `wp-content/plugins/`.
2. Ative **HG Spam** em *Plugins*.
3. Configure em *Configurações → HG Spam*.

O plugin não depende do Composer em produção: `vendor/` só contém o PHPUnit e não precisa ir para o servidor.

## Configuração

Tudo é feito em *Configurações → HG Spam*; nada fica fixo no código.

| Campo | O que faz |
|---|---|
| Servidor | `Ollama` ou `OpenRouter`. Os campos de cada um aparecem ao selecionar. |
| URL do Ollama | Endereço do servidor Ollama (padrão `http://localhost:11434`). |
| API key do OpenRouter | Chave usada no cabeçalho `Authorization`. |
| Modelos | Lista de modelos disponíveis, um por linha (padrão: `jev`, `clef`, `clef-flash`; no OpenRouter, com prefixo `cloudflare/`). |
| Modelo ativo | Modelo realmente usado. Salve após mudar a lista para o select atualizar. |
| Limite de spam (%) | É spam quando o score for maior ou igual a este valor. Padrão 80. |
| Instrução | Texto que descreve o que é spam. Os servidores limitam o tamanho (o `laya` aceita cerca de 550 caracteres); acima disso o servidor recusa e o formulário passa sem filtro. |
| Timeout (s) | Tempo máximo de espera pelo servidor (1 a 60). Padrão 10. |
| Debug | Anexa modelo, score e resposta bruta à mensagem do CF7. |

> **Atenção:** o Debug mostra a resposta do modelo para qualquer visitante que envie o formulário. Não deixe ligado em produção.

## Como funciona

1. O CF7 chama o filtro `wpcf7_spam`.
2. `Filter` monta o texto a analisar: uma linha `campo: valor` por campo, ignorando campos internos (`_*`) e vazios. Cada campo é cortado em 2000 caracteres; o total em 6000, preservando início e fim.
3. `Client` envia ao servidor e lê `answers.safe_to_run.noul` (probabilidade de "Sim" = spam).
4. Se `score * 100 >= limite`, o envio é marcado como spam e um registro com `agent: hg-spam` e o motivo vai para o log de spam do CF7.

### Falha aberta (fail-open)

Se o servidor estiver fora do ar, mal configurado ou devolver resposta inválida, o formulário **é enviado normalmente**, sem filtro. Para você saber disso:

- aparece um aviso vermelho no admin, com botão "Limpar aviso";
- o administrador recebe um e-mail, no máximo 1 vez por hora por tipo de erro;
- o aviso some sozinho na primeira consulta bem-sucedida.

Tipos de erro: `config`, `connection`, `http`, `invalid_json`, `invalid_response` e `internal`.

Se o Contact Form 7 não estiver ativo, o admin mostra um aviso e nenhum formulário é filtrado.

## Estrutura

```
hg-spam.php                      carrega as classes e registra os hooks
includes/class-filter.php        integração com o CF7 e decisão de spam
includes/class-client.php        cliente HTTP do servidor (Ollama/OpenRouter)
includes/class-settings.php      página de configurações e opções
includes/class-alerts.php        aviso no admin e e-mail de falha
includes/class-decision-exception.php   exceção com código do erro
tests/                           testes PHPUnit
```

## Testes

```bash
composer install
vendor/bin/phpunit
```
