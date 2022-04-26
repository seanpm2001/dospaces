<?php
/**
 * @link      https://www.vaersaagod.no/
 * @copyright Copyright (c) Værsågod
 * @license   MIT
 */

namespace vaersaagod\dospaces;

use Aws\CommandInterface;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client as AwsS3Client;

/**
 * Class S3Client
 *
 * Duplicate of craft\awss3\S3Client
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class S3Client extends AwsS3Client
{
    /**
     * @var callable callback for generating new config, including new credentials.
     */
    private $_generateNewConfig;

    /**
     * @var AwsS3Client the wrapped AWS client to use for all requests
     */
    private AwsS3Client $_wrappedClient;

    /**
     * @inheritdoc
     */
    public function __construct(array $args)
    {
        if (!empty($args['generateNewConfig'])) {
            $this->_generateNewConfig = $args['generateNewConfig'];
            unset($args['generateNewConfig']);
        }

        // Create an instance of parent class to use.
        $this->_wrappedClient = new parent($args);

        parent::__construct($args);
    }

    /**
     * @inheritdoc
     */
    public function execute(CommandInterface $command)
    {
        try {
            // Just try to execute
            return $this->_wrappedClient->execute($command);
        } catch (S3Exception $s3Exception) {
            // Attempt to get new credentials
            if ($s3Exception->getAwsErrorCode() === 'ExpiredToken') {
                $clientConfig = call_user_func($this->_generateNewConfig);
                $this->_wrappedClient = new parent($clientConfig);

                // Re-create the command to use the new credentials
                $newCommand = $this->getCommand($command->getName(), $command->toArray());
                return $this->_wrappedClient->execute($newCommand);
            }

            throw $s3Exception;
        }
    }

    /**
     * @inheritdoc
     */
    public function getCommand($name, array $args = [])
    {
        // Use the wrapped client which should have the latest credentials.
        return $this->_wrappedClient->getCommand($name, $args);
    }
}
