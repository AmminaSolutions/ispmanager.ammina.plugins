<?php
/**
 * @var \AmminaISP\Core\TemplateSynchronizer $this
 */
?>

set $allowseo 1;

{% if $PLATFORM == bitrix %}

if ($request_uri ~ ^/(bitrix|\.well-known)/) {
set $allowseo 0;
}

{% endif %}

if ($uri ~ ^/(500|502|503|504|403|404)\.(html|php)) {
set $allowseo 0;
}

{% if $SEO_SETTINGS_WWW == on %}

set $hostiswww 0;
if ($host ~* www\.(.*)) {
set $hostiswww 1;
}
set $hostiswww "${hostiswww}${allowseo}";
if ($hostiswww = "01") {
rewrite ^(.*)$ $scheme://www.$host$1 permanent;
}

{% endif %}

{% if $SEO_SETTINGS_NOWWW == on %}

set $hostiswww 0;
if ($host ~* www\.(.*)) {
set $host_without_www $1;
set $hostiswww 1;
}
set $hostiswww "${hostiswww}${allowseo}";
if ($hostiswww = "11") {
rewrite ^(.*)$ $scheme://$host_without_www$1 permanent;
}

{% endif %}

{% if $SEO_SETTINGS_SLASH == on %}

set $allow_slash_redir "";
if (!-f $request_filename) {
set $allow_slash_redir "1";
}
if ($request_uri !~ ^/robots.txt$) {
set $allow_slash_redir "${allow_slash_redir}1";
}
if ($request_uri ~ "^(.*)\.(?:php|html|jpg|jpeg|png|gif|webp|ttf|otf|eot|woff2|woff|pdf|txt|doc|docx|xls|xlsx|svg|js|css|mp3|ogg|mpe?g|avi|zip|gz|bz2?|rar|swf|ico|webp|mp4|webm)") {
set $allow_slash_redir 0;
}
set $allow_slash_redir "${allow_slash_redir}${allowseo}";
if ($allow_slash_redir = "111") {
rewrite ^(.*[^/])$ $1/ permanent;
}

{% endif %}

{% if $SEO_SETTINGS_NOSLASH == on %}

set $existsfile 1;
if (!-f $request_filename) {
set $existsfile 0;
}
set $existsfile "${existsfile}${allowseo}";
if ($existsfile = "01") {
rewrite ^/(.*)/$ /$1 permanent;
}

{% endif %}

{% if $SEO_SETTINGS_NOINDEX == on %}

set $isindexurl 0;
if ($request_uri ~ "^(.*)\/index\.(?:php|html)") {
set $isindexurl 1;
set $noindexurl $1$is_args$args;
}
set $isindexurl "${isindexurl}${allowseo}";
if ($isindexurl = "11")
{
return 301 $noindexurl;
}

{% endif %}

{% if $SEO_SETTINGS_NOMULTISLASH == on %}

set $ismultislash 0;
if ($request_uri ~ ^[^?]*//) {
set $ismultislash 1;
}
set $ismultislash "${ismultislash}${allowseo}";
if ($ismultislash = "11") {
rewrite ^ $uri permanent;
}

{% endif %}
