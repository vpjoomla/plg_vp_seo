<?php
/**
 * @package     VPJoomla.Plugin
 * @subpackage  System.Vpseo
 *
 * @copyright   Copyright (C) 2024 - 2026 VPJoomla. All rights reserved.
 * @license     GNU GPL v2 or later; see LICENSE.txt
 */

namespace VPJoomla\Plugin\System\Vpseo\Field;

\defined('_JEXEC') or die;

// 

use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

/**
 * Live social-card preview shown in the article editor.
 * Read-only: it mirrors what VP SEO will output to OpenGraph/Twitter and updates
 * as the editor types in the SEO Title / Social Description / Social Image fields.
 *
 * @since  2.6.0
 */
class VpseopreviewField extends FormField
{
    /**
     * @var  string
     */
    protected $type = 'Vpseopreview';

    /**
     * @inheritDoc
     */
    protected function getInput()
    {
        $plugin    = PluginHelper::getPlugin('system', 'vpseo');
        $params    = new Registry($plugin->params ?? '');
        $root      = preg_replace('#/administrator$#', '', rtrim(Uri::root(), '/'));
        $host      = Uri::getInstance()->getHost();

        $defImg    = trim((string) $params->get('default_image', ''));
        $defImgAbs = '';

        if ($defImg !== '') {
            $clean     = explode('#', $defImg)[0];
            $defImgAbs = preg_match('#^https?://#i', $clean) ? $clean : $root . '/' . ltrim($clean, '/');
        }

        $id         = $this->id;
        $hShare     = htmlspecialchars(Text::_('PLG_SYSTEM_VPSEO_PREVIEW_HEADING'), ENT_QUOTES, 'UTF-8');
        $hHint      = htmlspecialchars(Text::_('PLG_SYSTEM_VPSEO_PREVIEW_HINT'), ENT_QUOTES, 'UTF-8');
        $titlePh    = htmlspecialchars(Text::_('PLG_SYSTEM_VPSEO_PREVIEW_TITLE_PH'), ENT_QUOTES, 'UTF-8');
        $descPh     = htmlspecialchars(Text::_('PLG_SYSTEM_VPSEO_PREVIEW_DESC_PH'), ENT_QUOTES, 'UTF-8');
        $rootEsc    = htmlspecialchars($root, ENT_QUOTES, 'UTF-8');
        $hostEsc    = htmlspecialchars($host, ENT_QUOTES, 'UTF-8');
        $defImgEsc  = htmlspecialchars($defImgAbs, ENT_QUOTES, 'UTF-8');

        $css = <<<CSS
<style>
.vpseo-pp { max-width: 540px; }
.vpseo-pp__hint { color: #6b7280; font-size: .85rem; margin: 0 0 .5rem; }
.vpseo-pp__card { border: 1px solid #dadde1; border-radius: 8px; overflow: hidden; background: #fff; font-family: Helvetica, Arial, sans-serif; }
.vpseo-pp__img { width: 100%; aspect-ratio: 1.91 / 1; background: #e9eef3 center/cover no-repeat; }
.vpseo-pp__img.is-empty { display: flex; align-items: center; justify-content: center; color: #9aa4b2; font-size: .8rem; }
.vpseo-pp__img.is-empty::after { content: "1200 × 630"; }
.vpseo-pp__meta { padding: 10px 12px; border-top: 1px solid #dadde1; }
.vpseo-pp__domain { text-transform: uppercase; color: #606770; font-size: .72rem; letter-spacing: .2px; margin-bottom: 3px; }
.vpseo-pp__title { color: #1d2129; font-weight: 600; font-size: 1rem; line-height: 1.3; margin: 0 0 4px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.vpseo-pp__desc { color: #606770; font-size: .85rem; line-height: 1.35; margin: 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
</style>
CSS;

        $html = <<<HTML
<div class="vpseo-pp" id="{$id}" data-root="{$rootEsc}/" data-host="{$hostEsc}" data-default="{$defImgEsc}">
    <p class="vpseo-pp__hint">{$hHint}</p>
    <div class="vpseo-pp__card">
        <div class="vpseo-pp__img is-empty"></div>
        <div class="vpseo-pp__meta">
            <div class="vpseo-pp__domain">{$hostEsc}</div>
            <div class="vpseo-pp__title">{$titlePh}</div>
            <div class="vpseo-pp__desc">{$descPh}</div>
        </div>
    </div>
</div>
HTML;

        $js = <<<JS
<script>
(function () {
    function init() {
        var root = document.getElementById('{$id}');
        if (!root || root.dataset.vpseoInit) { return; }
        root.dataset.vpseoInit = '1';

        var siteRoot = root.dataset.root || '/';
        var host     = root.dataset.host || '';
        var defImg   = root.dataset.default || '';
        var imgEl    = root.querySelector('.vpseo-pp__img');
        var titleEl  = root.querySelector('.vpseo-pp__title');
        var descEl   = root.querySelector('.vpseo-pp__desc');
        var domEl    = root.querySelector('.vpseo-pp__domain');
        var titlePh  = titleEl.textContent;
        var descPh   = descEl.textContent;

        function val(id) { var e = document.getElementById(id); return e ? (e.value || '').trim() : ''; }
        function clean(p) { return p ? p.split('#')[0].split('?')[0] : ''; }
        function isSvg(p) { return /\.svg$/i.test(clean(p)); }
        function abs(p) {
            p = clean(p);
            if (!p) { return ''; }
            if (/^https?:\/\//i.test(p)) { return p; }
            return siteRoot.replace(/\/$/, '') + '/' + p.replace(/^\//, '');
        }
        function pickImg() {
            var cands = [val('jform_metadata_og_image'), val('jform_images_image_intro'), defImg];
            for (var i = 0; i < cands.length; i++) {
                if (cands[i] && !isSvg(cands[i])) { return abs(cands[i]); }
            }
            return '';
        }
        function update() {
            titleEl.textContent = val('jform_metadata_seo_title') || val('jform_title') || titlePh;
            descEl.textContent  = val('jform_metadata_og_description') || val('jform_metadesc') || descPh;
            domEl.textContent   = host;
            var img = pickImg();
            if (img) {
                imgEl.style.backgroundImage = 'url("' + img + '")';
                imgEl.classList.remove('is-empty');
            } else {
                imgEl.style.backgroundImage = '';
                imgEl.classList.add('is-empty');
            }
        }

        ['jform_metadata_seo_title', 'jform_title', 'jform_metadata_og_description',
         'jform_metadesc', 'jform_metadata_og_image', 'jform_images_image_intro'].forEach(function (id) {
            var e = document.getElementById(id);
            if (e) { e.addEventListener('input', update); e.addEventListener('change', update); }
        });

        update();
    }

    if (document.readyState !== 'loading') { init(); }
    else { document.addEventListener('DOMContentLoaded', init); }
})();
</script>
JS;

        return '<div class="vpseo-pp-wrap"><strong>' . $hShare . '</strong>' . $css . $html . $js . '</div>';
    }
}
