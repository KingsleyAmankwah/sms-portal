<?php


namespace SMSPortalExceptions;

class SMSPortalException extends \Exception
{
    const INVALID_REQUEST = 1001;
    const INVALID_TOKEN = 1002;
    const INVALID_UPLOAD_TYPE = 1003;
    const FILE_ERROR = 2001;
    const FILE_SIZE_ERROR = 2002;
    const FILE_TYPE_ERROR = 2003;
    const FILE_EMPTY = 2004;
    const INVALID_HEADERS = 2005;
    const CONTACT_REQUIRED_FIELDS = 3001;
    const INVALID_PHONE_FORMAT = 3002;
    const INVALID_EMAIL_FORMAT = 3003;
    const DUPLICATE_PHONE = 3004;
    const DUPLICATE_EMAIL = 3005;
    const DATABASE_ERROR = 4001;
    const NO_CONTACTS_ADDED = 5001;
    const INVALID_PARAMETER = 6001;
    const INVALID_ACTION = 6002;
    const INVALID_SESSION = 7001;

    public static function invalidRequest(): self
    {
        return new self('Invalid request method', self::INVALID_REQUEST);
    }

    public static function invalidToken(): self
    {
        return new self('Invalid CSRF token', self::INVALID_TOKEN);
    }

    public static function invalidUploadType(): self
    {
        return new self('Invalid upload type', self::INVALID_UPLOAD_TYPE);
    }

    public static function fileError(): self
    {
        return new self('No file uploaded or upload error', self::FILE_ERROR);
    }

    public static function fileSizeError($maxSize): self
    {
        return new self("File size exceeds {$maxSize}MB limit", self::FILE_SIZE_ERROR);
    }

    public static function fileTypeError(): self
    {
        return new self('Invalid file type. Please upload CSV or Excel file', self::FILE_TYPE_ERROR);
    }

    public static function fileEmpty(): self
    {
        return new self('File is empty', self::FILE_EMPTY);
    }

    public static function invalidHeaders(): self
    {
        return new self('File must contain Name and Phone Number columns', self::INVALID_HEADERS);
    }

    public static function requiredFields(): self
    {
        return new self('Name, phone number and contact group are required', self::CONTACT_REQUIRED_FIELDS);
    }

    public static function invalidPhoneFormat(): self
    {
        return new self('Invalid phone number format (e.g., +233123456789)', self::INVALID_PHONE_FORMAT);
    }
    public static function insufficientSMSBalance()
    {
        return new self('Insufficient SMS balance to send messages');
    }

    public static function invalidEmailFormat(): self
    {
        return new self('Invalid email format', self::INVALID_EMAIL_FORMAT);
    }

    public static function duplicateContact(): self
    {
        return new self('A contact with this name already exists', self::DUPLICATE_PHONE | self::DUPLICATE_EMAIL);
    }

    public static function duplicatePhone(): self
    {
        return new self('A contact with this phone number already exists', self::DUPLICATE_PHONE);
    }

    public static function duplicateEmail(): self
    {
        return new self('A contact with this email already exists', self::DUPLICATE_EMAIL);
    }

    public static function databaseError($message = 'Database operation failed'): self
    {
        return new self($message, self::DATABASE_ERROR);
    }

    public static function noContactsAdded($errors): self
    {
        return new self('No contacts were added. ' . $errors, self::NO_CONTACTS_ADDED);
    }

    public static function invalidParameter($param): self
    {
        return new self("Invalid parameter: {$param}", self::INVALID_PARAMETER);
    }

    public static function invalidDeleteAction()
    {
        return new self('Please select a valid action to perform, to continue');
    }

    public static function invalidAction(): self
    {
        return new self('Invalid action specified', self::INVALID_ACTION);
    }

    public static function invalidSession(): self
    {
        return new self('User session not authenticated', self::INVALID_SESSION);
    }

    public static function duplicateGroup()
    {
        return new self('A group with this name already exists');
    }

    public static function groupNotFound()
    {
        return new self('Group not found');
    }
}
