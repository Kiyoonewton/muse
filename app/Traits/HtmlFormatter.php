<?php

namespace App\Traits;

use voku\helper\HtmlDomParser;

trait HtmlFormatter
{
    use WordPress;

    /**
     * santize article to instant article format
     *
     * @param string $article
     * @param array $postDetails
     *
     * @return string
     */
    public function sanitizeContent($article, $postDetails)
    {
        $postContent = $article['content'];
        $postContent = str_replace('> <', '><', $postContent);

        /**
         * remove images from strong, a, p, header tags
         * remove iframes from p tags
         */
        $postContent = preg_replace(
            [
                '/<strong\b[^>]*>(<img[^>]*>)<\/strong>/i',
                '/<a\b[^>]*>(<img[^>]*>)<\/a>/i',
                '/<p\b[^>]*>(<img[^>]*>)<\/p>/i',
                '/<h[0-6]\b[^>]*>(<img[^>]*>)<\/h[0-6]>/i',
                '/<p\b[^>]*>(<iframe.*src=\"(.*)\".*><\/iframe>)<\/p>/i',
            ],
            '\1',
            $postContent
        );

        /* convert header 3-6 tags to h2 */
        $postContent = preg_replace('/<h[3-6]/i', '<h2', $postContent);
        $postContent = preg_replace('/<\/h[3-6]/i', '</h2', $postContent);

        /* wrap image in figure tags */
        $postContent = preg_replace('#<img[^>]*>#i', '<figure>$0</figure>', $postContent);

        /* wrap iframe in figure tags */
        $postContent = preg_replace('/<iframe.*src=\"(.*)\".*><\/iframe>/isU', "<figure class='op-interactive'>$0</figure>", $postContent);

        /* remove div and span tags */
        $postContent = preg_replace(['#<div[^>]*>#i', '/<\/div>/i', '#<span[^>]*>#i', '/<\/span>/i'], '', $postContent);

        $html = HtmlDomParser::str_get_html($postContent);
        $postContent = $html->outertext;
        $tags = [];
        foreach ($html->find('p') as $p) {
            array_push($tags, $p->outerHtml);
        }

        $postContent = $this->formatImageCaption($tags, $postContent);

        /* remove all arb article teasers */
        $postContent = preg_replace(
            [
                '/<h2\b[^>]*>(<span\b[^>]*>(<del\b[^>]*>(.*?)<\/del>)<\/span>)<\/h2>/i',
                '/<h2\b[^>]*>(<del\b[^>]*>(<span\b[^>]*>(.*?)<\/span>)<\/del>)<\/h2>/i',
                '/<h2\b[^>]*>(<a\b[^>]*>(.*?)<\/a>)<\/h2>/i',
                '/<del\b[^>]*>(.*?)<\/del>/i',
                '/<!--(.*)-->/i',
                '#<h[0-6]>(\s|&nbsp;|</?\s?br\s?/?>)*</?h[0-6]>#',
            ],
            '',
            $postContent
        );

        $postContent = $this->wpautop($postContent);

        /* remove unclosed opening p tags around h1 tags */
        $postContent = preg_replace('/<p\b[^>]*>(.*)<\/h1>/i', '\1</h1>', $postContent);

        /* social media embeds */
        $postContent = $this->formatSocialEmbeds($postContent, $postDetails);

        /* remove empty p and figure tags */
        $postContent = preg_replace(
            ['/\[{2}(.*?)\]{2}/is', '#<p>(\s|&nbsp;|</?\s?br\s?/?>)*</?p>#', '#<figure>(\s|&nbsp;|</?\s?br\s?/?>)*</?figure>#'],
            '',
            $postContent
        );

        return $postContent;
    }

    /**
     * Wrap image caption with figure tag
     *
     * @param array $tags
     * @param string $postContent
     *
     * @return string
     */
    private function formatImageCaption($tags, $postContent)
    {
        foreach ($tags as $p) {
            if (strpos($p, '[[imagecaption||')) {
                $newTag = str_replace('[[imagecaption||', "<figcaption class='op-vertical-below'>", $p);
                $newTag = str_replace(']]', '</figcaption>', $newTag);
                $new = HtmlDomParser::str_get_html($newTag);

                $caption = $new->find('figcaption');
                $figure = $new->find('figure');
                $figure->innertext = $figure->innertext[0].$caption->outertext[0];

                $postContent = str_replace(
                    $p,
                    "<figure>$figure->innertext</figure>",
                    $postContent
                );
            }
        }

        return $postContent;
    }

    /**
     * Handle formatting for social embeds
     * @param string $content
     *
     * @return string
     */
    private function formatSocialEmbeds($content, $postDetails)
    {
        $postContent = preg_replace('/<p\b[^>]*>(\[{2}(.*?)\]{2})<\/p>/i', '\1', $content);
        if (preg_match_all('/\[{2}(.*?)\]{2}/is', $postContent, $customTags)) {
            foreach ($customTags[0] as $customTag) {
                if (preg_match('/\b(\w*widget\w*)\b/', $customTag)) {
                    /* grab url embedded in custom tag */
                    $url = preg_replace(['/\b(\w*widget\w*)\b/', '/\[{2}/is', '/\]{2}/is', '/\|{2}/is'], '', $customTag);

                    $no_social_widget = false;

                    /* create iframes for each social media embed if available */
                    if (preg_match('/\b(\w*twitter\w*)\b/', $customTag)) {
                        $url =
              "<iframe class='column-width'>
              <blockquote class='twitter-tweet' data-lang='en-gb'>
                <a href='$url'></a>
                <script async src='//platform.twitter.com/widgets.js' charset='utf-8'></script>
              </blockquote>
            </iframe>";
                    } elseif (preg_match('/\b(\w*facebook\w*)\b/', $customTag)) {
                        $url =
              "<iframe class='no-margin'>
              <script src='https://connect.facebook.net/en_US/sdk.js#xfbml=1&amp;version=v2.5' async></script>
              <div class='fb-post'
                data-href='$url'
                data-show-text='true'
              >
              </div>
            </iframe>";
                    } elseif (preg_match('/\b(\w*instagram\w*)\b/', $customTag)) {
                        $url =
              "<iframe class='column-width'>
              <blockquote class='instagram-media' data-instgrm-permalink='$url' data-instgrm-version='12'>
                <a href='$url'></a>
                <p>A post shared by Instagram (@instagram)</p>
              </blockquote>
              <script async src='//www.instagram.com/embed.js'></script>
            </iframe>";
                    } elseif (preg_match('/\b(\w*tiktok\w*)\b/', $customTag)) {
                        $tiktokRegex = strstr($url, 'video/');
                        $tiktokVideoId = substr($tiktokRegex, 6);
                        $url =
              "<iframe class='column-width'>
                <blockquote class='tiktok-embed' cite='$url' data-video-id='$tiktokVideoId'>
                  <section>

                  </section>
                </blockquote>
                <script async src='https://www.tiktok.com/embed.js'></script>
              </iframe>";
                    } elseif (preg_match('/\b(\w*youtube\w*)\b/', $customTag)) {
                        $url = "<iframe class='column-width' width='560' height='315' src='$url'></iframe>";
                    } elseif (preg_match('/\b(\w*rumble\w*)\b/', $customTag)) {
                        $url = "<iframe class='column-width' class='column-width' width='640' height='360' src='$url' frameborder='0' allowfullscreen></iframe>";
                    } elseif (preg_match('/\b(\w*jwplayer\w*)\b/', $customTag)) {
                        $url_match = [];

                        preg_match('/\|{2}.*\|{2}(.*)\]\]/s', $customTag, $url_match);
                        $media_id = $url_match[1] ?? null;

                        $jw_player_script_url = "http://content.jwplatform.com/libraries/{$postDetails['jw_player_id']}.js";
                        $jw_player_dom_id = 'jwplayer';

                        $url = "
            <iframe class='column-width' allowfullscreen>
              <script type='text/javascript' src='{$jw_player_script_url}'></script>
              <div id='{$jw_player_dom_id}'></div>
              <script type='text/javascript'>
                const playerInstance = jwplayer('{$jw_player_dom_id}');
                playerInstance.setup({
                  playlist: 'https://cdn.jwplayer.com/v2/media/$media_id?format=json',
                  mediaid: '$media_id',
                  aspectRatio: '16:9',
                  width: '100%'
                });
                playerInstance.once('ready', () => {
                  const playButtonContainer = playerInstance.getContainer().getElementsByClassName('jw-display-icon-display')[0];
                  Object.assign(playButtonContainer.style, { display: 'flex', flexDirection: 'column', alignItems: 'center' });

                  const ctaElement = document.createElement('p');
                  ctaElement.innerHTML = 'Click To Play';
                  ctaElement.style.color = 'white';
                  playButtonContainer.appendChild(ctaElement);

                  const playButton = playButtonContainer.getElementsByClassName('jw-icon')[0];
                  const playButtonColor = window.getComputedStyle(playButton).color;
                  Object.assign(playButton.style, { color: 'white', backgroundColor: playButtonColor, borderRadius: '50%', padding: '14%', transform: 'scale(0.8)' });

                  playerInstance.getContainer().getElementsByClassName('jw-controls-backdrop')[0].style.background = 'transparent';
                });
              </script>
            </iframe>";
                    } else {
                        $no_social_widget = true;
                    }

                    /* Insert the created iframe into the content */
                    $postContent = $no_social_widget
            ? $postContent
            : str_replace($customTag, "<figure class='op-interactive'>$url</figure>", $postContent);
                }
            }
        }

        return $postContent;
    }
}
