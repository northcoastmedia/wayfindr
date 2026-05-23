<?php

test('php runtime meets the server baseline', function (): void {
    expect(PHP_VERSION_ID)->toBeGreaterThanOrEqual(80300);
});
