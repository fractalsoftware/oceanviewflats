<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Fulfillment;

interface HostRegistryEmailRendererInterface
{
    public function renderSubject(GuestRegistrySubmission $submission): string;

    public function renderPlainText(
        GuestRegistrySubmission $submission,
        string $doorCode
    ): string;

    public function renderHtml(
        GuestRegistrySubmission $submission,
        string $doorCode
    ): string;
}
