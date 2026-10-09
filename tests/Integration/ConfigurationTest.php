<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Test\Integration;

use Msstc4Symfony\LogicBundle\DependencyInjection\CompilerPass\WrapActionsPass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

final class ConfigurationTest extends TestCase
{
    public function testDefaults(): void
    {
        $container = TestContainer::compile();

        self::assertSame('short', $container->getParameter(WrapActionsPass::DEFAULT_PROFILE_PARAMETER));
        self::assertSame(409, $container->getParameter(WrapActionsPass::CONFLICT_STATUS_PARAMETER));
        self::assertSame(['ttl' => 300, 'retry_count' => 0, 'retry_delay_ms' => 0], $this->profiles($container->getParameter(WrapActionsPass::PROFILES_PARAMETER))['long']);
    }

    public function testCustomProfilesMergeWithBuiltIns(): void
    {
        $container = TestContainer::compile(['lock' => ['default_profile' => 'pdf', 'profiles' => ['pdf' => ['ttl' => 600], 'short' => ['ttl' => 2]]]]);
        $profiles = $this->profiles($container->getParameter(WrapActionsPass::PROFILES_PARAMETER));

        self::assertSame(['long', 'pdf', 'short'], $this->sortedKeys($profiles));
        self::assertSame(['ttl' => 2, 'retry_count' => 0, 'retry_delay_ms' => 0], $profiles['short']);
    }

    public function testUnknownDefaultProfileIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('"nope"');

        TestContainer::compile(['lock' => ['default_profile' => 'nope']]);
    }

    #[TestWith([302])]
    #[TestWith([600])]
    #[TestWith(['409'])]
    public function testConflictStatusMustBeAnHttpErrorStatus(int|string $status): void
    {
        $this->expectException(InvalidConfigurationException::class);

        TestContainer::compile(['lock' => ['conflict_status_code' => $status]]);
    }

    #[TestWith([0])]
    #[TestWith([-1])]
    public function testProfileTtlMustBePositive(int $ttl): void
    {
        $this->expectException(InvalidConfigurationException::class);

        TestContainer::compile(['lock' => ['profiles' => ['x' => ['ttl' => $ttl]]]]);
    }

    /**
     * @return array<mixed>
     */
    private function profiles(mixed $parameter): array
    {
        self::assertIsArray($parameter);

        return $parameter;
    }

    /**
     * @param array<mixed> $profiles
     *
     * @return list<array-key>
     */
    private function sortedKeys(array $profiles): array
    {
        $keys = array_keys($profiles);
        sort($keys);

        return $keys;
    }
}
