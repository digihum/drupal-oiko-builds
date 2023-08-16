<?php

declare(strict_types=1);

namespace Laminas\ServiceManager\Exception;

use InvalidArgumentException as SplInvalidArgumentException;
use Laminas\ServiceManager\AbstractFactoryInterface;
use Laminas\ServiceManager\Initializer\InitializerInterface;

<<<<<<< HEAD
use function get_class;
=======
>>>>>>> feature/medmus-d9
use function gettype;
use function is_object;
use function sprintf;

/**
 * @inheritDoc
 */
class InvalidArgumentException extends SplInvalidArgumentException implements ExceptionInterface
{
<<<<<<< HEAD
    /**
     * @param mixed $initializer
     */
    public static function fromInvalidInitializer($initializer): self
=======
    public static function fromInvalidInitializer(mixed $initializer): self
>>>>>>> feature/medmus-d9
    {
        return new self(sprintf(
            'An invalid initializer was registered. Expected a callable or an'
            . ' instance of "%s"; received "%s"',
            InitializerInterface::class,
<<<<<<< HEAD
            is_object($initializer) ? get_class($initializer) : gettype($initializer)
        ));
    }

    /**
     * @param mixed $abstractFactory
     */
    public static function fromInvalidAbstractFactory($abstractFactory): self
=======
            is_object($initializer) ? $initializer::class : gettype($initializer)
        ));
    }

    public static function fromInvalidAbstractFactory(mixed $abstractFactory): self
>>>>>>> feature/medmus-d9
    {
        return new self(sprintf(
            'An invalid abstract factory was registered. Expected an instance of or a valid'
            . ' class name resolving to an implementation of "%s", but "%s" was received.',
            AbstractFactoryInterface::class,
<<<<<<< HEAD
            is_object($abstractFactory) ? get_class($abstractFactory) : gettype($abstractFactory)
=======
            is_object($abstractFactory) ? $abstractFactory::class : gettype($abstractFactory)
>>>>>>> feature/medmus-d9
        ));
    }
}
