<?php

namespace App\Enums;

enum EmployeeDocumentType: string
{
    case Tazkira = 'tazkira';
    case Passport = 'passport';
    case Visa = 'visa';
    case WorkPermit = 'work_permit';
    case Contract = 'contract';
    case Degree = 'degree';
    case Certificate = 'certificate';
    case PoliceClearance = 'police_clearance';
    case Medical = 'medical';
    case DrivingLicense = 'driving_license';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Tazkira => __('enums.employee_document_type.tazkira'),
            self::Passport => __('enums.employee_document_type.passport'),
            self::Visa => __('enums.employee_document_type.visa'),
            self::WorkPermit => __('enums.employee_document_type.work_permit'),
            self::Contract => __('enums.employee_document_type.contract'),
            self::Degree => __('enums.employee_document_type.degree'),
            self::Certificate => __('enums.employee_document_type.certificate'),
            self::PoliceClearance => __('enums.employee_document_type.police_clearance'),
            self::Medical => __('enums.employee_document_type.medical'),
            self::DrivingLicense => __('enums.employee_document_type.driving_license'),
            self::Other => __('enums.employee_document_type.other'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
