/// <reference types="node" />
import assert from 'node:assert/strict';
import { test } from 'vite-plus/test';
import LogController from '../../resources/js/actions/App/Http/Controllers/LogController';
import { tabSlide } from '../../resources/js/hooks/use-tab-slide';
import { settingsTabs } from '../../resources/js/layouts/settings/tabs';
import { edit as editSafety } from '../../resources/js/routes/safety';
import { show as showTerminal } from '../../resources/js/routes/terminal';

const settings = settingsTabs.map((tab) => tab.href.url);

const lanes = [
    LogController.show('following').url,
    LogController.show('latest').url,
    LogController.show('trending').url,
];

test('every settings tab slides forward to the tab on its right and back to the tab on its left', () => {
    for (const [index, from] of settings.entries()) {
        for (const [otherIndex, to] of settings.entries()) {
            if (index !== otherIndex) {
                assert.equal(
                    tabSlide(from, to),
                    otherIndex > index ? 'forward' : 'back',
                    `${from} to ${to}`,
                );
            }
        }
    }
});

test('lanes slide in their tab order', () => {
    assert.equal(tabSlide(lanes[0], lanes[1]), 'forward');
    assert.equal(tabSlide(lanes[2], lanes[0]), 'back');
});

test('staying on a tab or leaving its group does not slide', () => {
    assert.equal(tabSlide(showTerminal().url, showTerminal().url), null);
    assert.equal(tabSlide(lanes[1], editSafety().url), null);
    assert.equal(tabSlide(showTerminal().url, '/notifications'), null);
});
