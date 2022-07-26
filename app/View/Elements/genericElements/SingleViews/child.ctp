<?php
/*
 * create single view child index
 *
 */
    if (!empty($child['url_params'])) {
        if (!is_array($child['url_params'])) {
            $child['url_params'] = [$child['url_params']];
        }
        foreach ($child['url_params'] as $i => $url_param) {
            $child['url'] = str_replace('{{' . $i . '}}', Hash::extract($data, $url_param)[0], $child['url']);
        }
    }
    echo $this->element('genericElements/accordion', [
        'url' => $child['url'],
        'title' => $child['title'],
        'elementId' => empty($child['elementId']) ? null : $child['elementId'],
        'open' => !empty($child['open']) ? $child['open'] : false
    ]);
