<?php

namespace App\Enums;

enum VendorDocumentType: string
{
    case Proposal = 'proposal';
    case Quotation = 'quotation';
    case Agreement = 'agreement';
    case Contract = 'contract';
    case InvoiceReference = 'invoice_reference';
    case Brief = 'brief';
    case TechnicalSpec = 'technical_spec';
    case Amendment = 'amendment';
    case Supporting = 'supporting';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Proposal => 'Proposal',
            self::Quotation => 'Penawaran Harga',
            self::Agreement => 'Perjanjian',
            self::Contract => 'Kontrak',
            self::InvoiceReference => 'Referensi Invoice',
            self::Brief => 'Brief Layanan',
            self::TechnicalSpec => 'Spesifikasi Teknis',
            self::Amendment => 'Amandemen',
            self::Supporting => 'Dokumen Pendukung',
            self::Other => 'Lainnya',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Proposal => 'badge-info',
            self::Quotation => 'badge-info',
            self::Agreement => 'badge-neutral',
            self::Contract => 'badge-neutral',
            self::InvoiceReference => 'badge-neutral',
            self::Brief => 'badge-neutral',
            self::TechnicalSpec => 'badge-neutral',
            self::Amendment => 'badge-warning',
            self::Supporting => 'badge-muted',
            self::Other => 'badge-muted',
        };
    }
}
