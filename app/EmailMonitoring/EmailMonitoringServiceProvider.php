<?php

namespace App\EmailMonitoring;

use App\EmailMonitoring\Contracts\InboundEmailNormalizerInterface;
use App\EmailMonitoring\Contracts\InvoiceCandidateExtractorInterface;
use App\EmailMonitoring\Contracts\MailboxConnectorFactoryInterface;
use App\EmailMonitoring\Contracts\OcrProviderInterface;
use App\EmailMonitoring\Contracts\VendorEmailMatcherInterface;
use App\EmailMonitoring\Events\InvoiceExtractionLowConfidence;
use App\EmailMonitoring\Events\InvoiceExtractionRecorded;
use App\EmailMonitoring\Events\VendorEmailMatchedForMonitoring;
use App\EmailMonitoring\Infrastructure\Extraction\HeuristicInvoiceCandidateExtractor;
use App\EmailMonitoring\Infrastructure\MailboxConnectorFactory;
use App\EmailMonitoring\Infrastructure\Matching\DatabaseVendorEmailMatcher;
use App\EmailMonitoring\Infrastructure\Normalization\DefaultInboundEmailNormalizer;
use App\EmailMonitoring\Infrastructure\Ocr\NoopOcrProvider;
use App\EmailMonitoring\Listeners\NotifyInvoiceExtractionLowConfidence;
use App\EmailMonitoring\Listeners\NotifyInvoiceExtractionRecorded;
use App\EmailMonitoring\Listeners\NotifyVendorEmailMatched;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class EmailMonitoringServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MailboxConnectorFactoryInterface::class, MailboxConnectorFactory::class);
        $this->app->bind(InboundEmailNormalizerInterface::class, DefaultInboundEmailNormalizer::class);
        $this->app->bind(VendorEmailMatcherInterface::class, DatabaseVendorEmailMatcher::class);
        $this->app->bind(InvoiceCandidateExtractorInterface::class, HeuristicInvoiceCandidateExtractor::class);
        $this->app->bind(OcrProviderInterface::class, NoopOcrProvider::class);
    }

    public function boot(): void
    {
        Event::listen(VendorEmailMatchedForMonitoring::class, NotifyVendorEmailMatched::class);
        Event::listen(InvoiceExtractionRecorded::class, NotifyInvoiceExtractionRecorded::class);
        Event::listen(InvoiceExtractionLowConfidence::class, NotifyInvoiceExtractionLowConfidence::class);
    }
}
