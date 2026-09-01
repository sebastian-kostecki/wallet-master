<?php

declare(strict_types=1);

namespace App\Support\Deposits;

final readonly class DepositClassification
{
    public function __construct(
        public DepositClassificationIntent $intent,
        public DepositClassificationConfidence $confidence,
    ) {}

    public static function none(): self
    {
        return new self(
            DepositClassificationIntent::None,
            DepositClassificationConfidence::Auto,
        );
    }

    public static function with(
        DepositClassificationIntent $intent,
        DepositClassificationConfidence $confidence,
    ): self {
        return new self($intent, $confidence);
    }

    public function isAutoOpen(): bool
    {
        return $this->intent === DepositClassificationIntent::Open
            && $this->confidence === DepositClassificationConfidence::Auto;
    }

    public function isAutoClose(): bool
    {
        return $this->intent === DepositClassificationIntent::ClosePrincipal
            && $this->confidence === DepositClassificationConfidence::Auto;
    }

    public function isCandidateOpen(): bool
    {
        return $this->intent === DepositClassificationIntent::Open
            && $this->confidence === DepositClassificationConfidence::Candidate;
    }

    public function isCandidateClose(): bool
    {
        return $this->intent === DepositClassificationIntent::ClosePrincipal
            && $this->confidence === DepositClassificationConfidence::Candidate;
    }
}
