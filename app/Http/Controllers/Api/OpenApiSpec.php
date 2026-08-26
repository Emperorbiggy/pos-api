<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use OpenApi\Attributes as OA;

#[OA\OpenApi(
    info: new OA\Info(
        version: '1.0.0',
        title: 'ECG POS API',
        description: 'Production REST API for JWT authentication, OIRS terminal validation, payment notification, and invoice generation.'
    ),
    servers: [
        new OA\Server(url: 'https://ecgposapi.electroniccollectionsecg.com', description: 'Production API server (ECG)'),
        new OA\Server(url: 'https://ecg.easinovation.com.ng', description: 'Production API server (Easinovation)'),
        new OA\Server(url: 'http://localhost:8000', description: 'Local API server'),
    ],
    security: [
        ['bearerAuth' => []],
    ]
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    description: 'Paste your JWT as: Bearer {token}',
    name: 'Authorization',
    in: 'header',
    bearerFormat: 'JWT',
    scheme: 'bearer'
)]
#[OA\Schema(
    schema: 'User',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'ECG POS Admin'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin@example.com'),
        new OA\Property(property: 'terminal_id', description: 'POS terminal identifier. Unique across users.', type: 'string', maxLength: 50, nullable: true, example: '1234567890'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true, example: '2026-07-06T10:30:00+01:00'),
    ]
)]
#[OA\Schema(
    schema: 'RegisterRequest',
    required: ['name', 'email', 'terminal_id', 'password', 'password_confirmation'],
    type: 'object',
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'ECG POS Admin'),
        new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255, example: 'admin@example.com'),
        new OA\Property(property: 'terminal_id', description: 'POS terminal identifier. Must not already be assigned to another user.', type: 'string', maxLength: 50, example: '1234567890'),
        new OA\Property(property: 'password', type: 'string', minLength: 8, example: 'password123'),
        new OA\Property(property: 'password_confirmation', type: 'string', minLength: 8, example: 'password123'),
    ]
)]
#[OA\Schema(
    schema: 'LoginRequest',
    required: ['email', 'password'],
    type: 'object',
    properties: [
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin@example.com'),
        new OA\Property(property: 'password', type: 'string', example: 'password123'),
    ]
)]
#[OA\Schema(
    schema: 'AuthTokenResponse',
    type: 'object',
    properties: [
        new OA\Property(property: 'data', type: 'object', properties: [
            new OA\Property(property: 'access_token', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...'),
            new OA\Property(property: 'token_type', type: 'string', example: 'bearer'),
            new OA\Property(property: 'expires_in', type: 'integer', example: 3600),
            new OA\Property(property: 'user', ref: '#/components/schemas/User'),
        ]),
    ]
)]
#[OA\Schema(
    schema: 'UserResponse',
    type: 'object',
    properties: [
        new OA\Property(property: 'data', ref: '#/components/schemas/User'),
    ]
)]
#[OA\Schema(
    schema: 'ValidateIpnRequest',
    required: ['ipn', 'terminal_id'],
    type: 'object',
    properties: [
        new OA\Property(property: 'ipn', type: 'string', maxLength: 50, example: '931713074597'),
        new OA\Property(property: 'terminal_id', type: 'string', maxLength: 50, example: '1234567890'),
    ]
)]
#[OA\Schema(
    schema: 'PaymentValidationResponse',
    type: 'object',
    properties: [
        new OA\Property(property: 'data', type: 'object', properties: [
            new OA\Property(property: 'ipn', type: 'string', example: '931713074597'),
            new OA\Property(property: 'terminal_id', type: 'string', example: '1234567890'),
            new OA\Property(property: 'customer', type: 'object', nullable: true, properties: [
                new OA\Property(property: 'id', type: 'string', nullable: true, example: '79453121'),
                new OA\Property(property: 'ipn', type: 'string', nullable: true, example: '931713074597'),
                new OA\Property(property: 'name', type: 'string', nullable: true, example: 'John Doe'),
                new OA\Property(property: 'email', type: 'string', nullable: true, example: 'john@example.com'),
                new OA\Property(property: 'phone', type: 'string', nullable: true, example: '08030000000'),
                new OA\Property(property: 'address', type: 'string', nullable: true, example: 'Osogbo, Osun State'),
            ]),
        ]),
    ]
)]
#[OA\Schema(
    schema: 'PaymentNotificationRequest',
    required: ['ipn', 'amount_paid', 'terminal_id', 'paid_at'],
    type: 'object',
    properties: [
        new OA\Property(property: 'ipn', type: 'string', maxLength: 50, example: '931713074597'),
        new OA\Property(property: 'amount_paid', type: 'number', format: 'float', minimum: 0.01, example: 100),
        new OA\Property(property: 'terminal_id', type: 'string', maxLength: 50, example: '1234567890'),
        new OA\Property(property: 'paid_at', type: 'string', format: 'date-time', example: '2026-07-06 10:30:00'),
    ]
)]
#[OA\Schema(
    schema: 'PaymentNotificationResponse',
    type: 'object',
    properties: [
        new OA\Property(property: 'data', type: 'object', properties: [
            new OA\Property(property: 'ipn', type: 'string', example: '931713074597'),
            new OA\Property(property: 'amount_paid', type: 'number', format: 'float', example: 100),
            new OA\Property(property: 'terminal_id', type: 'string', example: '1234567890'),
            new OA\Property(property: 'paid_at', type: 'string', format: 'date-time', example: '2026-07-06T10:30:00+01:00'),
            new OA\Property(property: 'reference', type: 'string', nullable: true, example: 'TRX-123456'),
            new OA\Property(property: 'status', description: 'Payment status reported by OIRS; the merchant payment record is moved to this value. Null when OIRS returns none.', type: 'string', nullable: true, example: 'paid'),
        ]),
    ]
)]
#[OA\Schema(
    schema: 'GenerateInvoiceRequest',
    required: ['revenue_code', 'agency_code', 'amount', 'pid', 'payment_type'],
    type: 'object',
    properties: [
        new OA\Property(property: 'revenue_code', type: 'string', maxLength: 50, example: '4020154'),
        new OA\Property(property: 'agency_code', type: 'string', maxLength: 50, example: '4100000'),
        new OA\Property(property: 'amount', type: 'number', format: 'float', minimum: 0.01, example: 100),
        new OA\Property(property: 'pid', type: 'string', maxLength: 50, example: '79453121'),
        new OA\Property(property: 'payment_type', type: 'string', enum: ['individual', 'corporate'], example: 'individual'),
    ]
)]
#[OA\Schema(
    schema: 'InvoiceResponse',
    type: 'object',
    properties: [
        new OA\Property(property: 'data', type: 'object', properties: [
            new OA\Property(property: 'ipn', type: 'string', example: '873648221564'),
            new OA\Property(property: 'authorization_url', type: 'string', format: 'uri', example: 'https://erms.cloudware.ng/gateway/checkout/873648221564/checkout'),
        ]),
    ]
)]
#[OA\Schema(
    schema: 'UpdateProfileRequest',
    description: 'Send only the fields you want to change; omitted fields are left as they are.',
    type: 'object',
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'ECG POS Admin'),
        new OA\Property(property: 'terminal_id', description: 'Must not already belong to another merchant.', type: 'string', maxLength: 50, example: '204401PG'),
    ]
)]
#[OA\Schema(
    schema: 'Payment',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'ipn', type: 'string', example: '331622459317'),
        new OA\Property(property: 'terminal_id', type: 'string', example: '204401PG'),
        new OA\Property(property: 'status', description: 'Starts as pending at validation, then moves to whatever OIRS reports when the terminal sends its payment notification.', type: 'string', example: 'pending'),
        new OA\Property(property: 'amount', type: 'number', format: 'float', example: 12000),
        new OA\Property(property: 'total_amount', type: 'number', format: 'float', example: 12000),
        new OA\Property(property: 'amount_paid', type: 'number', format: 'float', example: 0),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Osun State Harmonised Bill'),
        new OA\Property(property: 'reference', type: 'string', nullable: true, example: 'TRX-556677'),
        new OA\Property(property: 'paid_at', type: 'string', format: 'date-time', nullable: true, example: '2026-08-24T10:30:00+01:00'),
        new OA\Property(property: 'customer', type: 'object', properties: [
            new OA\Property(property: 'id', type: 'string', nullable: true, example: '454368'),
            new OA\Property(property: 'ipn', type: 'string', nullable: true, example: null),
            new OA\Property(property: 'name', type: 'string', nullable: true, example: 'OLUJIDE JEREMIAH AMBEE'),
            new OA\Property(property: 'email', type: 'string', nullable: true, example: null),
            new OA\Property(property: 'phone', type: 'string', nullable: true, example: '07050710801'),
            new OA\Property(property: 'address', type: 'string', nullable: true, example: 'ZY1, BOLORUNDURO OKE IYANU, ILESA.'),
        ]),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true, example: '2026-08-24T10:00:00+01:00'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true, example: '2026-08-24T10:30:00+01:00'),
    ]
)]
#[OA\Schema(
    schema: 'PaymentCollectionResponse',
    type: 'object',
    properties: [
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Payment')),
        new OA\Property(property: 'links', type: 'object', properties: [
            new OA\Property(property: 'first', type: 'string', nullable: true, example: 'https://ecg.easinovation.com.ng/api/v1/payments?page=1'),
            new OA\Property(property: 'last', type: 'string', nullable: true, example: 'https://ecg.easinovation.com.ng/api/v1/payments?page=4'),
            new OA\Property(property: 'prev', type: 'string', nullable: true, example: null),
            new OA\Property(property: 'next', type: 'string', nullable: true, example: 'https://ecg.easinovation.com.ng/api/v1/payments?page=2'),
        ]),
        new OA\Property(property: 'meta', type: 'object', properties: [
            new OA\Property(property: 'current_page', type: 'integer', example: 1),
            new OA\Property(property: 'from', type: 'integer', nullable: true, example: 1),
            new OA\Property(property: 'last_page', type: 'integer', example: 4),
            new OA\Property(property: 'per_page', type: 'integer', example: 25),
            new OA\Property(property: 'to', type: 'integer', nullable: true, example: 25),
            new OA\Property(property: 'total', type: 'integer', example: 87),
        ]),
    ]
)]
#[OA\Schema(
    schema: 'MessageResponse',
    type: 'object',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Logged out successfully.'),
    ]
)]
#[OA\Schema(
    schema: 'ErrorResponse',
    type: 'object',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.'),
    ]
)]
#[OA\Schema(
    schema: 'ValidationErrorResponse',
    type: 'object',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'The given data was invalid.'),
        new OA\Property(property: 'errors', type: 'object', example: ['email' => ['The email field is required.']]),
    ]
)]
final class OpenApiSpec {}
