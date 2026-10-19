<?php

declare(strict_types=1);

namespace OceanViewFlats\Domain\Fulfillment;

use InvalidArgumentException;

/**
 * Immutable typed value object representing a registered occupant.
 */
final class OccupantDetails
{
    public const VALID_DOC_TYPES = [
        'Passport',
        'National ID',
        'Driver License',
        'Other ID',
        'Cédula de Ciudadanía',
        'Tarjeta de Identidad',
        'Registro Civil',
        'Cédula de Extranjería',
    ];

    public const MINOR_DOC_TYPES = [
        'Tarjeta de Identidad',
        'Registro Civil',
    ];

    /**
     * Official Condominium Reception Portal (Huésped Manager) Document Code Translations.
     */
    public const PORTAL_DOC_TYPE_MAP = [
        'Passport' => 'PA',
        'Cédula de Ciudadanía' => 'CC',
        'Tarjeta de Identidad' => 'TI',
        'Registro Civil' => 'RC',
        'National ID' => 'DE',
        'Driver License' => 'DE',
        'Other ID' => 'DE',
        'Cédula de Extranjería' => 'CE',
    ];

    public readonly int $index;
    public readonly string $name;
    public readonly int $age;
    public readonly string $docType;
    public readonly string $docNum;
    public readonly ?string $email;
    public readonly ?string $firstName;
    public readonly ?string $lastName;
    public readonly ?string $phone;
    public readonly ?string $country;
    public readonly ?string $middleName;
    public readonly ?string $secondLastName;

    public function __construct(
        int $index,
        ?string $name = null,
        int $age = 0,
        string $docType = '',
        string $docNum = '',
        ?string $email = null,
        ?string $firstName = null,
        ?string $lastName = null,
        ?string $phone = null,
        ?string $country = null,
        ?string $middleName = null,
        ?string $secondLastName = null
    ) {
        if ($index < 1) {
            throw new InvalidArgumentException('Occupant index must be at least 1.');
        }

        // Clean name fields
        $cleanFirstName = $firstName !== null && trim($firstName) !== '' ? trim($firstName) : null;
        $cleanLastName = $lastName !== null && trim($lastName) !== '' ? trim($lastName) : null;
        $cleanMiddleName = $middleName !== null && trim($middleName) !== '' ? trim($middleName) : null;
        $cleanSecondLastName = $secondLastName !== null && trim($secondLastName) !== '' ? trim($secondLastName) : null;

        $trimmedName = $name !== null ? trim($name) : '';

        // Resolve name, firstName, lastName with backwards compatibility
        if ($cleanFirstName !== null || $cleanLastName !== null) {
            if ($trimmedName === '') {
                $trimmedName = trim((string) implode(' ', array_filter([
                    $cleanFirstName,
                    $cleanMiddleName,
                    $cleanLastName,
                    $cleanSecondLastName,
                ])));
            }
        } elseif ($trimmedName !== '') {
            // Intelligent split of legacy full name into first name and surname
            $parts = preg_split('/\s+/', $trimmedName) ?: [];
            if (count($parts) >= 2) {
                $cleanFirstName = array_shift($parts);
                $cleanLastName = implode(' ', $parts);
            } elseif (count($parts) === 1) {
                $cleanFirstName = $parts[0];
                $cleanLastName = $parts[0];
            }
        }

        if (strlen($trimmedName) < 2 || strlen($trimmedName) > 100) {
            throw new InvalidArgumentException('Occupant name must be between 2 and 100 characters.');
        }

        if ($age < 0 || $age > 120) {
            throw new InvalidArgumentException('Occupant age must be between 0 and 120.');
        }

        if (!in_array($docType, self::VALID_DOC_TYPES, true)) {
            throw new InvalidArgumentException(sprintf('Invalid document type: %s.', $docType));
        }

        $trimmedDoc = trim($docNum);
        if (strlen($trimmedDoc) < 2 || strlen($docNum) > 50) {
            throw new InvalidArgumentException('Occupant document number must be between 2 and 50 characters.');
        }

        $cleanEmail = null;
        if ($email !== null) {
            $trimmedEmail = trim($email);
            if ($trimmedEmail === '' || !filter_var($trimmedEmail, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
                throw new InvalidArgumentException('Occupant email must be a valid email address up to 100 characters.');
            }
            $cleanEmail = $trimmedEmail;
        }

        $cleanPhone = null;
        if ($phone !== null) {
            $trimmedPhone = trim($phone);
            if ($trimmedPhone !== '') {
                if (strlen($trimmedPhone) < 6 || strlen($trimmedPhone) > 30) {
                    throw new InvalidArgumentException('Occupant phone must be between 6 and 30 characters.');
                }
                $cleanPhone = $trimmedPhone;
            }
        }

        $cleanCountry = self::normalizeCountry($country);

        $this->index = $index;
        $this->name = $trimmedName;
        $this->age = $age;
        $this->docType = $docType;
        $this->docNum = $trimmedDoc;
        $this->email = $cleanEmail;
        $this->firstName = $cleanFirstName;
        $this->lastName = $cleanLastName;
        $this->middleName = $cleanMiddleName;
        $this->secondLastName = $cleanSecondLastName;
        $this->phone = $cleanPhone;
        $this->country = $cleanCountry;
    }

    /**
     * Translates document type string into condominium portal code (PA, CC, TI, RC, DE, CE).
     */
    public static function mapDocTypeToPortalCode(string $docType): string
    {
        if (isset(self::PORTAL_DOC_TYPE_MAP[$docType])) {
            return self::PORTAL_DOC_TYPE_MAP[$docType];
        }

        $upper = strtoupper(trim($docType));
        if (in_array($upper, ['PA', 'CC', 'TI', 'RC', 'DE', 'CE', 'CD'], true)) {
            return $upper;
        }

        return 'DE';
    }

    /**
     * Returns the 2-character Condominium Administration Portal document code.
     */
    public function getPortalDocType(): string
    {
        return self::mapDocTypeToPortalCode($this->docType);
    }

    /**
     * Determines whether the occupant is a minor under Colombian Law 1581 (Art. 7) / Law 679 of 2001.
     */
    public function isMinor(): bool
    {
        return $this->age < 18 || in_array($this->docType, self::MINOR_DOC_TYPES, true);
    }

    /**
     * Normalizes country strings to uppercase Condominium Administration Portal standards.
     */
    public static function normalizeCountry(?string $country): ?string
    {
        if ($country === null) {
            return null;
        }
        $c = mb_strtoupper(trim($country), 'UTF-8');
        if ($c === '') {
            return null;
        }

        // Transliterate accented characters to plain uppercase ASCII
        $c = str_replace(
            ['Á', 'É', 'Í', 'Ó', 'Ú', 'Ü', 'Ñ'],
            ['A', 'E', 'I', 'O', 'U', 'U', 'N'],
            $c
        );

        $aliasMap = [
            'SPAIN' => 'ESPANA',
            'UNITED STATES' => 'ESTADOS UNIDOS',
            'USA' => 'ESTADOS UNIDOS',
            'US' => 'ESTADOS UNIDOS',
            'BRAZIL' => 'BRASIL',
            'GERMANY' => 'ALEMANIA',
            'FRANCE' => 'FRANCIA',
            'ITALY' => 'ITALIA',
            'UNITED KINGDOM' => 'REINO UNIDO',
            'UK' => 'REINO UNIDO',
            'NETHERLANDS' => 'PAISES BAJOS',
            'SWITZERLAND' => 'SUIZA',
            'JAPAN' => 'JAPON',
            'OTHER' => 'OTRO',
        ];

        return $aliasMap[$c] ?? $c;
    }

    /**
     * @return array{
     *     index: int,
     *     name: string,
     *     first_name: ?string,
     *     last_name: ?string,
     *     middle_name: ?string,
     *     second_last_name: ?string,
     *     phone: ?string,
     *     country: ?string,
     *     age: int,
     *     doc_type: string,
     *     doc_num: string,
     *     email: ?string
     * }
     */
    public function toArray(): array
    {
        return [
            'index' => $this->index,
            'name' => $this->name,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'middle_name' => $this->middleName,
            'second_last_name' => $this->secondLastName,
            'phone' => $this->phone,
            'country' => $this->country,
            'age' => $this->age,
            'doc_type' => $this->docType,
            'doc_num' => $this->docNum,
            'email' => $this->email,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data, int $index = 1): self
    {
        $idx = (int)($data['index'] ?? $index);
        $name = trim((string)(
            $data['name']
            ?? $data['full_name']
            ?? $data["guest_name_{$idx}"]
            ?? $data['guest_name']
            ?? ''
        ));

        $firstName = isset($data['first_name']) || isset($data['firstName']) || isset($data['guest_first_name']) || isset($data["guest_first_name_{$idx}"])
            ? (string)($data['first_name'] ?? $data['firstName'] ?? $data['guest_first_name'] ?? $data["guest_first_name_{$idx}"])
            : null;
        $lastName = isset($data['last_name']) || isset($data['lastName']) || isset($data['guest_last_name']) || isset($data["guest_last_name_{$idx}"])
            ? (string)($data['last_name'] ?? $data['lastName'] ?? $data['guest_last_name'] ?? $data["guest_last_name_{$idx}"])
            : null;
        $middleName = isset($data['middle_name']) || isset($data['middleName']) || isset($data['guest_middle_name']) || isset($data["guest_middle_name_{$idx}"]) || isset($data['sname'])
            ? (string)($data['middle_name'] ?? $data['middleName'] ?? $data['guest_middle_name'] ?? $data["guest_middle_name_{$idx}"] ?? $data['sname'])
            : null;
        $secondLastName = isset($data['second_last_name']) || isset($data['secondLastName']) || isset($data['guest_second_last_name']) || isset($data["guest_second_last_name_{$idx}"]) || isset($data['mname'])
            ? (string)($data['second_last_name'] ?? $data['secondLastName'] ?? $data['guest_second_last_name'] ?? $data["guest_second_last_name_{$idx}"] ?? $data['mname'])
            : null;
        $phone = isset($data['phone']) || isset($data['guest_phone']) || isset($data["guest_phone_{$idx}"])
            ? (string)($data['phone'] ?? $data['guest_phone'] ?? $data["guest_phone_{$idx}"])
            : null;
        $country = isset($data['country']) || isset($data['guest_country']) || isset($data["guest_country_{$idx}"])
            ? (string)($data['country'] ?? $data['guest_country'] ?? $data["guest_country_{$idx}"])
            : null;

        $age = (int)($data['age'] ?? $data["guest_age_{$idx}"] ?? 0);
        $docType = trim((string)($data['doc_type'] ?? $data['docType'] ?? $data["guest_doc_type_{$idx}"] ?? ''));
        $docNum = trim((string)($data['doc_num'] ?? $data['docNum'] ?? $data['doc_number'] ?? $data["guest_doc_num_{$idx}"] ?? ''));

        $rawEmail = $data['email'] ?? $data["guest_email_{$idx}"] ?? null;
        $email = $rawEmail !== null && trim((string)$rawEmail) !== '' ? (string)$rawEmail : null;

        return new self(
            index: $idx,
            name: $name !== '' ? $name : null,
            age: $age,
            docType: $docType,
            docNum: $docNum,
            email: $email,
            firstName: $firstName,
            lastName: $lastName,
            phone: $phone,
            country: $country,
            middleName: $middleName,
            secondLastName: $secondLastName
        );
    }
}
