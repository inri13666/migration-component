<?php

namespace Okvpn\Bundle\MigrationBundle\Migration\Loader;

use Doctrine\ORM\EntityManager;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Bridge\Doctrine\DataFixtures\ContainerAwareLoader;

use Okvpn\Bundle\MigrationBundle\Entity\DataFixture;
use Okvpn\Bundle\MigrationBundle\Migration\Sorter\DataFixturesSorter;
use Okvpn\Bundle\MigrationBundle\Migration\UpdateDataFixturesFixture;
use Okvpn\Bundle\MigrationBundle\Fixture\VersionedFixtureInterface;
use Okvpn\Bundle\MigrationBundle\Fixture\LoadedFixtureVersionAwareInterface;

/**
 * @deprecated since 1.2 and will be removed in 1.3. Fixtures package will be moved to another separate repository,
 *             it step needed to reduces the count of not necessary dependencies
 */
class DataFixturesLoader extends ContainerAwareLoader
{
    /** @var EntityManager */
    protected $em;

    /** @var array */
    protected $loadedFixtures;

    /** @var \ReflectionProperty */
    protected $ref;

    /**
     * Constructor.
     *
     * @param EntityManager      $em
     * @param ContainerInterface $container
     */
    public function __construct(EntityManager $em, ContainerInterface $container)
    {
        parent::__construct($container);

        $this->em = $em;
    }

    /**
     * @inheritdoc
     */
    public function getFixtures()
    {
        $sorter   = new DataFixturesSorter();
        $fixtures = $sorter->sort($this->getAllFixtures());

        // remove already loaded fixtures
        foreach ($fixtures as $key => $fixture) {
            if ($this->isFixtureAlreadyLoaded($fixture)) {
                unset($fixtures[$key]);
            }
        }

        // add a special fixture to mark new fixtures as "loaded"
        if (!empty($fixtures)) {
            $toBeLoadFixtureClassNames = [];
            foreach ($fixtures as $fixture) {
                $version = null;
                if ($fixture instanceof VersionedFixtureInterface) {
                    $version = $fixture->getVersion();
                }
                $toBeLoadFixtureClassNames[get_class($fixture)] = $version;
            }

            $updateFixture = new UpdateDataFixturesFixture();
            $updateFixture->setDataFixtures($toBeLoadFixtureClassNames);
            $fixtures[get_class($updateFixture)] = $updateFixture;
        }

        return $fixtures;
    }

    /**
     * Determines whether the given data fixture is already loaded or not
     *
     * @param object $fixtureObject
     *
     * @return bool
     */
    protected function isFixtureAlreadyLoaded($fixtureObject)
    {
        if (!$this->loadedFixtures) {
            $this->loadedFixtures = [];

            $loadedFixtures = $this->em->getRepository('OkvpnMigrationBundle:DataFixture')->findAll();
            /** @var DataFixture $fixture */
            foreach ($loadedFixtures as $fixture) {
                $this->loadedFixtures[$fixture->getClassName()] = $fixture->getVersion() ?: '0.0';
            }
        }

        $alreadyLoaded = false;

        if (isset($this->loadedFixtures[get_class($fixtureObject)])) {
            $alreadyLoaded = true;
            $loadedVersion = $this->loadedFixtures[get_class($fixtureObject)];
            if ($fixtureObject instanceof VersionedFixtureInterface
                && version_compare($loadedVersion, $fixtureObject->getVersion()) == -1
            ) {
                if ($fixtureObject instanceof LoadedFixtureVersionAwareInterface) {
                    $fixtureObject->setLoadedVersion($loadedVersion);
                }
                $alreadyLoaded = false;
            }
        }

        return $alreadyLoaded;
    }

    /**
     * @return array
     */
    protected function getAllFixtures()
    {
        if (!$this->ref) {
            $this->ref = new \ReflectionProperty('Doctrine\Common\DataFixtures\Loader', 'fixtures');
            $this->ref->setAccessible(true);
        }

        return $this->ref->getValue($this);
    }
}
