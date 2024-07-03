<?php

declare(strict_types=1);

namespace Flow\Azure\SDK\BlobService\CopyBlob;

use Flow\Azure\SDK\{
    BlobService,
    BlobService\DeleteBlob\DeleteSnapshots,
    BlobService\DeleteBlob\DeleteType,
    EndpointOptions,
    Endpoints\UserAgentHeader
};

final class CopyBlobOptions implements EndpointOptions
{
    use UserAgentHeader;

    private ?DeleteSnapshots $deleteSnapshots = null;

    private ?DeleteType $deleteType = null;

    private ?string $leaseId = null;

    private ?string $requestId = null;

    private ?string $snapshot = null;

    private ?int $timeoutSeconds = null;

    private ?string $version = BlobService::VERSION;

    private ?string $versionId = null;

    public function toHeaders() : array
    {
        $headers = [];

        if ($this->version !== null) {
            $headers['x-ms-version'] = $this->version;
        }

        $headers['User-Agent'] = $this->userAgentHeader();

        if ($this->requestId !== null) {
            $headers['x-ms-client-request-id'] = $this->requestId;
        }

        if ($this->leaseId !== null) {
            $headers['x-ms-lease-id'] = $this->leaseId;
        }

        if ($this->snapshot === null) {
            if ($this->deleteSnapshots !== null) {
                $headers['x-ms-delete-snapshots'] = $this->deleteSnapshots->value;
            }
        }

        return $headers;
    }

    public function toURIParameters() : array
    {
        $uriParameters = [];

        if ($this->versionId !== null) {
            $uriParameters['versionId'] = $this->versionId;
        }

        if ($this->timeoutSeconds !== null) {
            $uriParameters['timeout'] = $this->timeoutSeconds;
        }

        if ($this->snapshot !== null) {
            $uriParameters['snapshot'] = $this->snapshot;
        }

        if ($this->deleteType !== null) {
            $uriParameters['deleteType'] = $this->deleteType->value;
        }

        return $uriParameters;
    }

    public function withLeaseId(string $leaseId) : self
    {
        $this->leaseId = $leaseId;

        return $this;
    }

    public function withRequestId(string $requestId) : self
    {
        $this->requestId = $requestId;

        return $this;
    }

    public function withSnapshot(string $snapshot) : self
    {
        $this->snapshot = $snapshot;

        return $this;
    }

    public function withTimeoutSeconds(int $timeoutSeconds) : self
    {
        $this->timeoutSeconds = $timeoutSeconds;

        return $this;
    }

    public function withVersion(string $version) : self
    {
        $this->version = $version;

        return $this;
    }

    public function withVersionId(string $versionId) : self
    {
        $this->versionId = $versionId;

        return $this;
    }
}