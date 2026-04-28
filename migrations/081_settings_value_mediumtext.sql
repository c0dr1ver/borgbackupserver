-- Allow generated branding assets, including 512x512 favicon PNGs, to fit safely.
ALTER TABLE settings MODIFY `value` MEDIUMTEXT DEFAULT NULL;
