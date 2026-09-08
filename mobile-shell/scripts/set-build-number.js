#!/usr/bin/env node
'use strict';

/**
 * Cordova before_prepare hook: stamp an always-increasing build number into
 * ios-CFBundleVersion and android-versionCode so store uploads never collide.
 *
 * Number = whole minutes since the Unix epoch (monotonic, unique per minute).
 * ~29 million today, so it stays well under Android's versionCode ceiling of
 * 2,100,000,000 for thousands of years.
 *
 * We deliberately do NOT use the CI's own counter (e.g. Appflow's CI_BUILD_NUMBER):
 * those are small numbers (7, 10, 11, …) that fall BELOW version codes Play has
 * already recorded from earlier uploads, so they collide with "version code already
 * used". The epoch-minute value is always far larger and always increasing, so it
 * can never collide again. An explicit FORCE_VERSION_CODE override is honored if you
 * ever need to pin a specific value.
 *
 * The human-facing marketing version (widget @version, e.g. 0.1.0) is left
 * untouched — bump that yourself when you cut a real release. Build numbers
 * only ever appear in the Appflow / App Store Connect / Play dashboards, never
 * to end users, so the large value is cosmetic.
 */
module.exports = function (context) {
  const fs = require('fs');
  const path = require('path');

  const configPath = path.join(context.opts.projectRoot, 'config.xml');
  let xml = fs.readFileSync(configPath, 'utf8');

  const forced = parseInt(process.env.FORCE_VERSION_CODE, 10);
  const build = Number.isFinite(forced) && forced > 0 ? forced : Math.floor(Date.now() / 60000);

  const before = xml;
  xml = xml.replace(/android-versionCode="\d+"/, `android-versionCode="${build}"`);
  xml = xml.replace(/ios-CFBundleVersion="[\d.]+"/, `ios-CFBundleVersion="${build}"`);

  if (xml === before) {
    console.warn('[set-build-number] no version attributes found to replace — check config.xml');
  } else {
    fs.writeFileSync(configPath, xml, 'utf8');
    console.log(`[set-build-number] ios-CFBundleVersion & android-versionCode set to ${build}`);
  }
};
