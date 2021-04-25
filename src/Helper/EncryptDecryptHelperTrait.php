<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Helper;

trait EncryptDecryptHelperTrait
{
    /**
     * Encrypt the provided data using the specified key
     * This is used for import and exporting of files mainly
     *
     * @param string $data The data to encrypt.
     * @param string $key The key to use to encrypt the data.
     *
     * @return string|bool The encrypted data or false if fails.
     */
    protected function encrypt($data, $key)
    {
        return openssl_encrypt($data, 'AES-256-CBC', hash('sha512', $key));
    }

    /**
     * Decrypt the provided data using the specified key
     * This is used for import and exporting of files mainly
     *
     * @param string $data The data to decrypt.
     * @param string $key The key to use to decrypt the data.
     *
     * @return string|bool The decrypted data.
     */
    protected function decrypt(string $data, string $key)
    {
        $decrypted = openssl_decrypt($data, 'AES-256-CBC', hash('sha512', $key));
        if ($decrypted === false) {
            $decrypted = openssl_decrypt($data, 'AES-256-ECB', hash('md5', $key));
        }

        return $decrypted;
    }
}
