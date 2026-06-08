<?php
/** @var \Kirby\Cms\App $kirby */

$apiKey = $kirby->option('matthiaswyler.live-visitors.apiKey');
if (!$apiKey) return;
if ($kirby->user() !== null) return;
if ($page->intendedTemplate()->name() === 'error') return;

$interval   = $kirby->option('matthiaswyler.live-visitors.interval', 30);
$pluginRoot = dirname(__DIR__);
?>
<style><?= file_get_contents($pluginRoot . '/assets/live-visitors.css') ?></style>
<div
    id="live-visitors"
    class="live-visitors"
    data-api="<?= $kirby->url('api') ?>/live-visitors"
    data-interval="<?= esc($interval, 'attr') ?>"
    aria-live="polite"
    aria-label="Live visitors"
>
    <span class="live-visitors__dots"></span>
    <span class="live-visitors__count"></span>
</div>
<script><?= file_get_contents($pluginRoot . '/assets/live-visitors.js') ?></script>
