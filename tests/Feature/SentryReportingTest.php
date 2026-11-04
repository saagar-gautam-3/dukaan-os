<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\SentryEventScrubber;
use App\Support\SentryExceptionReporter;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Mockery;
use RuntimeException;
use Sentry\Event;
use Sentry\ExceptionDataBag;
use Sentry\Laravel\Facade as Sentry;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SentryReportingTest extends TestCase
{
    public function test_empty_dsn_keeps_reporting_disabled_without_a_sentry_client(): void
    {
        config(['sentry.dsn' => '']);
        Sentry::shouldReceive('captureException')->never();

        report(new RuntimeException('A local exception'));
        self::assertSame('', config('sentry.dsn'));
    }

    public function test_only_unexpected_exceptions_reach_the_reporting_hook(): void
    {
        $exception = new RuntimeException('Unexpected production failure');
        $reporter = Mockery::mock(SentryExceptionReporter::class);
        $reporter->shouldReceive('report')->once()->with($exception);
        $this->app->instance(SentryExceptionReporter::class, $reporter);

        report($exception);
        report(ValidationException::withMessages(['phone' => 'Invalid phone']));
        report(new AuthenticationException);
        report(new HttpException(403));
        report(new HttpException(404));
    }

    public function test_event_scrubber_removes_request_and_personal_data(): void
    {
        $event = Event::createEvent()
            ->setRequest([
                'url' => 'https://shop.example.test/sales?customer=Alice&amount=2500',
                'headers' => [
                    'Authorization' => 'Bearer secret',
                    'Cookie' => 'session=secret',
                    'Accept' => 'text/html',
                ],
                'cookies' => ['session' => 'secret'],
                'data' => ['password' => 'secret', 'token' => 'secret', 'phone' => '9841234567', 'email' => 'a@example.test'],
                'query_string' => 'customer=Alice&amount=2500',
            ])
            ->setExtra(['customer_name' => 'Alice', 'amount' => '2500'])
            ->setContext('customer', ['name' => 'Alice', 'total' => '2500'])
            ->setTags(['shop_id' => '12', 'user_id' => '34', 'customer_name' => 'Alice'])
            ->setMessage('Alice paid 2500');
        $event->setExceptions([new ExceptionDataBag(new RuntimeException('Alice paid 2500'))]);

        $scrubbed = SentryEventScrubber::scrub($event);

        self::assertNotNull($scrubbed);
        self::assertSame('https://shop.example.test/sales', $scrubbed->getRequest()['url']);
        self::assertArrayNotHasKey('cookies', $scrubbed->getRequest());
        self::assertArrayNotHasKey('data', $scrubbed->getRequest());
        self::assertArrayNotHasKey('query_string', $scrubbed->getRequest());
        self::assertSame(['Accept' => 'text/html'], $scrubbed->getRequest()['headers']);
        self::assertSame([], $scrubbed->getExtra());
        self::assertSame(['shop_id' => '12', 'user_id' => '34'], $scrubbed->getTags());
        self::assertSame('Unexpected server error', $scrubbed->getMessage());
        self::assertSame('Unexpected server error', $scrubbed->getExceptions()[0]->getValue());
        self::assertSame('[Filtered]', $scrubbed->getContexts()['customer']['name']);
    }
}
