<?php

namespace Tests\Unit;

use Illuminate\Auth\TokenGuard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\HeaderUtils;

final class DependencySecurityRegressionTest extends TestCase
{
    public function test_non_string_token_credentials_never_reach_the_user_provider(): void
    {
        $provider = $this->createMock(UserProvider::class);
        $provider->expects($this->never())->method('retrieveByCredentials');
        foreach ([[1], ['nested' => 'synthetic'], 1, true] as $credential) {
            $guard = new TokenGuard($provider, Request::create('/', 'POST', ['api_token' => $credential]));
            $this->assertNull($guard->user());
            $this->assertFalse($guard->validate(['api_token' => $credential]));
        }
    }

    public function test_header_fix_preserves_whitespace_separator_and_cache_control(): void
    {
        $this->assertSame(['foo', 'bar'], HeaderUtils::split('foo bar', ' '));
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_CACHE_CONTROL' => 'public'.str_repeat(' ', 20).', max-age=60']);
        $this->assertTrue($request->headers->hasCacheControlDirective('public'));
        $this->assertSame('60', $request->headers->getCacheControlDirective('max-age'));
    }
}
