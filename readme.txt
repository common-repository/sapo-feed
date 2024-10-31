=== SAPO Feed ===

Author:            SAPO
Author URI:        https://www.sapo.pt/
Requires at least: 4.6
Tested up to:      6.5.3
Stable tag:        trunk
Requires PHP:      7.0
License:           GPLv3
License URI:       https://www.gnu.org/licenses/gpl-3.0.html
Contributors:      portalsapo

Este plugin gera uma feed num formato compatível com serviços SAPO.

== Description ==

Este plugin gera uma feed simples, processando posts publicados no seu site num formato compatível com serviços SAPO.

Depois de ativado, a feed completa fica acessível em -- por exemplo -- https://omeusite.pt/feed/sapo

É possível filtrar conteúdo das seguintes formas:

 - Conteúdo de uma categoria: https://omeusite.pt/feed/sapo?category=destaques
 - Conteúdo de múltiplas categorias: https://omeusite.pt/feed/sapo?category=economia,cultura
 - Conteúdo de uma etiqueta: https://omeusite.pt/feed/sapo?tags=governo
 - Conteúdo de múltiplas etiquetas: https://omeusite.pt/feed/sapo?tags=teatro,cinema

Um pedido com múltiplas categorias e etiquetas devolve todos os artigos que pertençam a pelo menos uma das categorias e que tenham pelo menos uma das etiquetas pedidas. Há um limite de 20 posts que pode ser alterado através do ecrã de opções.

Para autores que necessitem de modificar o conteúdo dos seus artigos antes (ou depois) do processamento efectuado pelo plugin, dois filtros são disponibilizados:

 - `sapo_feed_handle_post_content_before`: recebe o conteúdo de um post, para ser alterado antes do processamento.
 - `sapo_feed_handle_post_content_after`: recebe o objecto que representa o post processado, para ser alterado antes da criação da feed, e o objecto original que representa o post.

== Screenshots ==

1. O ecrã de opções.
2. Inserção de informação geográfica.

== Changelog ==

= 2.3.1 =

 - Melhorias na construção da feed.

= 2.3.0 =

 - Adicionado suporte para filtros: 'sapo_feed_handle_post_content_before', 'sapo_feed_handle_post_content_after'.

= 2.2.1 =

 - Melhorias de suporte ao editor clássico.

= 2.2.0 =

 - Melhorias na construção da feed.

= 2.1.2 =

 - Revalidação de compatibilidade.

= 2.1.1 =

 - Correções de CSS.

= 2.1.0 =

 - Adicionado suporte para inserção de informação geográfica no editor clássico.

= 2.0.1 =

 - Correção de erros na seleção de concelhos das regiões autónomas.

= 2.0.0 =

 - Adicionado suporte para informação geográfica.

= 1.2.2 =

 - Melhorias na construção da feed, no que se refere a títulos.

= 1.2.1 =

 - Melhorias na construção da feed.

= 1.2.0 =

 - Adição da possibilidade de pedir conteúdo de múltiplas categorias com apenas um pedido.

= 1.1.6 =

 - Correções de erros. Melhorias de estabilidade.

= 1.1.5 =

 - Melhorias de segurança e performance.

= 1.1.4 =

 - Atualização da documentação.

= 1.1.3 =

 - Filtrar conteúdo programático dos posts.

= 1.1.2 =

 - Reforçar tolerância a erros no processamento de cada post.

= 1.1.1 =

 - Correções.

= 1.1.0 =

 - Adição de suporte para tipos de posts personalizados.

= 1.0.2 =

 - Remoção de formatação na lead dos posts.

= 1.0.1 =

 - Circunscrever conteúdos apenas a posts publicados.

= 1.0.0 =

 - Versão inicial.

