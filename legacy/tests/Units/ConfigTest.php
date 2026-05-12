<?php

describe("Config priority chain", function () {
    afterEach(function () {
        Config::load([]); // reset between tests
    });

    test("default value returned when nothing else set", function () {
        Config::load(['foo' => 'bar']);
        expect(Config::get('foo'))->toBe('bar');
    });

    test("json config overrides default", function () {
        file_put_contents(TEST_CONFIG_DIR . '/config.json', json_encode(['foo' => 'jsonbar']));
        Config::load(['foo' => 'bar'], jsonFile: TEST_CONFIG_DIR . '/config.json');
        expect(Config::get('foo'))->toBe('jsonbar');
    });

    test("env file overrides json config", function () {
        file_put_contents(TEST_CONFIG_DIR . '/config.json', json_encode(['foo' => 'jsonbar']));
        file_put_contents(TEST_CONFIG_DIR . '/.env', "FOO=envbar\n");
        Config::load(
            ['foo' => 'bar'],
            jsonFile: TEST_CONFIG_DIR . '/config.json',
            envFiles: [TEST_CONFIG_DIR . '/.env'],
        );
        expect(Config::get('foo'))->toBe('envbar');
    });

    test("second env file overrides first (tests/.env beats .env)", function () {
        file_put_contents(TEST_CONFIG_DIR . '/.env', "FOO=envbar\n");
        file_put_contents(TEST_CONFIG_DIR . '/tests.env', "FOO=testenvbar\n");
        Config::load(
            ['foo' => 'bar'],
            envFiles: [TEST_CONFIG_DIR . '/.env', TEST_CONFIG_DIR . '/tests.env'],
        );
        expect(Config::get('foo'))->toBe('testenvbar');
    });

    test("system env overrides env file (CLI: FOO=linebar ./script.php)", function () {
        file_put_contents(TEST_CONFIG_DIR . '/.env', "FOO=envbar\n");
        putenv('FOO=linebar');
        Config::load(['foo' => 'bar'], envFiles: [TEST_CONFIG_DIR . '/.env']);
        $result = Config::get('foo');
        putenv('FOO'); // cleanup
        expect($result)->toBe('linebar');
    });

    test("query param overrides system env", function () {
        putenv('FOO=linebar');
        $_GET['foo'] = 'querybar';
        Config::load(['foo' => 'bar'], withQueryParams: true);
        $result = Config::get('foo');
        putenv('FOO');
        unset($_GET['foo']);
        expect($result)->toBe('querybar');
    });

    test("kebab-case query param maps to snake_case key", function () {
        $_GET['my-var'] = 'querybar';
        Config::load(['my_var' => 'default'], withQueryParams: true);
        $result = Config::get('my_var');
        unset($_GET['my-var']);
        expect($result)->toBe('querybar');
    });

    test("unknown query param cannot inject new key", function () {
        $_GET['injected'] = 'evil';
        Config::load([], withQueryParams: true);
        $result = Config::get('injected');
        unset($_GET['injected']);
        expect($result)->toBeNull();
    });

    test("get() returns fallback when key absent", function () {
        Config::load([]);
        expect(Config::get('missing', 'fallback'))->toBe('fallback');
    });
});
