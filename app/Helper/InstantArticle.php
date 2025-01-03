<?php

namespace App\Helper;

use App\Traits\HtmlFormatter;

class InstantArticle
{
    use HtmlFormatter;

    /**
     * @var array
     */
    private $jsEmbeds = [];

    /**
     * @var array
     */
    private $siteDetails = null;

    public function __construct($article, $domain, $slug = '')
    {
        $this->article = $article;
        $this->slug = $slug;
        $this->domain = $domain;
        $this->siteDetails = $this->getSitesDetails($domain);
    }

    /**
     * Gets html formatted for instant article
     *
     * @return string
     */
    public function getHtml()
    {
        $postDetails = $this->getPostData($this->article);
        $postContent = $this->sanitizeContent($this->article, $postDetails);
        $this->getJsEmbeds($postDetails);
        $iaHtml = $this->createHtml($postDetails, $postContent);

        return $iaHtml;
    }

    /**
     * Get details of article
     *
     * @param string $article
     *
     * @return array
     */
    private function getPostData($article)
    {
        $postDetails = [];
        $postDetails['url'] = 'https://'.$this->domain.$this->slug;
        $postDetails['title'] = $article['title'];
        $postDetails['tags'] = [];

        $tags = $article['tags'];

        if (is_array($tags)) {
            foreach ($tags as $tag) {
                $postDetails['tags'][] = $tag;
            }
        }

        $postDetails['category'] = $article['category_name'];
        $postDetails['author'] = ucwords($article['author_uuid']);
        $postDetails['featured_img_url'] = $article['featured_image'];
        $postDetails['published_time'] = $article['created_at'];
        $postDetails['ISO_8601_date'] = $article['created_at'];
        $postDetails['adnw_placement_id'] = $this->siteDetails['ia']['adnw_placement_id'];
        $postDetails['recirculation_ad_id'] = $this->siteDetails['ia']['recirculation_ad_id'];
        $postDetails['ga_id'] = $this->siteDetails['ia']['ga_id'];
        $postDetails['jw_player_id'] = $this->siteDetails['jw_player_id'];

        return $postDetails;
    }

    /**
     * Create Html for instant article
     *
     * @param array $postDetails
     * @param string $postContent
     *
     * @return string
     */
    public function createHtml($postDetails, $postContent)
    {
        return
      "<!doctype html>
    <html>
      <head>
        <link rel='canonical' href='{$postDetails['url']}'/>
        <meta charset='utf-8'/>
        <meta property='op:generator' content='facebook-instant-articles-sdk-php'/>
        <meta property='op:generator:version' content='1.10.2'/>
        <meta property='op:generator:transformer' content='facebook-instant-articles-sdk-php'/>
        <meta property='op:generator:transformer:version' content='1.10.2'/>
        <meta property='op:markup_version' content='v1.0'/>
        <meta property='fb:use_automatic_ad_placement' content='enable=true ad_density=default'/>"
      .(isset($postDetails['recirculation_ad_id'])
        ? "<meta property='fb:op-recirculation-ads' content='placement_id={$postDetails['recirculation_ad_id']}'/>"
        : null).
      '
      </head>
      <body>
        <article>
          <header>'
      .($postDetails['featured_img_url']
        ? "<figure><img src='{$postDetails['featured_img_url']}'/></figure>"
        : null).
      "<h1>{$postDetails['title']}</h1>
            <time class='op-published' datetime='{$postDetails['ISO_8601_date']}'>{$postDetails['published_time']}</time>
            <address><a>{$postDetails['author']}</a></address>
            <h3 class='op-kicker'>{$postDetails['category']}</h3>
            <figure class='op-ad'>
              <iframe src='https://www.facebook.com/adnw_request?placement={$postDetails['adnw_placement_id']}&amp;adtype=banner300x250' width='300' height='250'></iframe>
            </figure>
          </header>
          {$postContent}
        </article>
      </body>
    </html>";
    }

    /**
     * Get Js embeds for the article
     *
     * @param array $postDetails
     *
     * @return string
     */
    private function getJsEmbeds($postDetails)
    {
        $this->jsEmbeds['ga'] =
      "<figure class='op-tracker'>
      <iframe>
        <script>
        (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
          (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
          m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
        })(window,document,'script','https://www.google-analytics.com/analytics.js','ga');
        ga('create', '{$postDetails['ga_id']}', 'auto');
        ga('send', 'pageview');
        </script>
      </iframe>
    </figure>";

        $this->jsEmbeds['quantserve'] =
      "<figure class='op-tracker'>
      <iframe>
        <script>
          var ezt = ezt ||[];
          (function(){
            var elem = document.createElement('script');
            elem.src = (document.location.protocol == 'https:' ? 'https://secure' : 'http://pixel') + '.quantserve.com/aquant.js?a=p-8j_G7YA1NwWw5';
            elem.async = true;
            elem.type = 'text/javascript';
            var scpt = document.getElementsByTagName('script')[0];
            scpt.parentNode.insertBefore(elem,scpt);
          }());
          ezt.push({qacct: 'p-8j_G7YA1NwWw5',
            uid: ''
          });
        </script>
        <noscript>
          <img src='//pixel.quantserve.com/pixel/p-8j_G7YA1NwWw5.gif' style='display: none;' border='0' height='1' width='1' alt='Quantcast'/>
        </noscript>
      </iframe>
    </figure>";
    }
}
