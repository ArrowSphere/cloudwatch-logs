<?php

declare(strict_types=1);

namespace ArrowSphere\CloudWatchLogs\Tests\Processor\ArsHeader;

use ArrowSphere\CloudWatchLogs\Processor\ArsHeader\ArsHeaderManager;
use ArrowSphere\CloudWatchLogs\Processor\ArsHeader\ArsRequestIdentifierEnum;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\UuidFactoryInterface;
use Ramsey\Uuid\UuidInterface;

/**
 * Class ArsHeaderManagerTest
 */
class ArsHeaderManagerTest extends TestCase
{
    private UuidFactoryInterface $factory;

    public function setUp(): void
    {
        $this->factory = $this->getMockBuilder(UuidFactoryInterface::class)->getMock();
        $this->factory->method('uuid4')->willReturnOnConsecutiveCalls(
            $this->getMockForUuid('00000000-0000-0000-0000-0000AAAA1111'),
            $this->getMockForUuid('11111111-1111-1111-1111-0000AAAA1111'),
        );
    }

    public function tearDown(): void
    {
        unset(
            $_SERVER[ArsRequestIdentifierEnum::ARS_CORRELATION_ID],
            $_SERVER[ArsRequestIdentifierEnum::ARS_REQUEST_ID],
            $_SERVER[ArsRequestIdentifierEnum::ARS_PARENT_ID],
        );
    }

    public function providerCreation(): array
    {
        return [
            'empty data' => [
                'data' => [],
                'expected' => [
                    'correlationId' => '00000000-0000-0000-0000-0000AAAA1111',
                    'requestId' => '00000000-0000-0000-0000-0000AAAA1111',
                    'parentId' => '',
                ],
            ],
            'known correlationId' => [
                'data' => [
                    ArsRequestIdentifierEnum::ARS_CORRELATION_ID => '22222222-2222-2222-2222-0000AAAA1111',
                ],
                'expected' => [
                    'correlationId' => '22222222-2222-2222-2222-0000AAAA1111',
                    'requestId' => '00000000-0000-0000-0000-0000AAAA1111',
                    'parentId' => '',
                ],
            ],
            'known correlationId and parentId' => [
                'data' => [
                    ArsRequestIdentifierEnum::ARS_CORRELATION_ID => '33333333-3333-3333-3333-0000AAAA1111',
                    ArsRequestIdentifierEnum::ARS_PARENT_ID => '44444444-4444-4444-4444-0000AAAA1111',
                ],
                'expected' => [
                    'correlationId' => '33333333-3333-3333-3333-0000AAAA1111',
                    'requestId' => '00000000-0000-0000-0000-0000AAAA1111',
                    'parentId' => '',
                ],
            ],
            'all 3 ids known' => [
                'data' => [
                    ArsRequestIdentifierEnum::ARS_CORRELATION_ID => '55555555-5555-5555-5555-0000AAAA1111',
                    ArsRequestIdentifierEnum::ARS_REQUEST_ID => '66666666-6666-6666-6666-0000AAAA1111',
                    ArsRequestIdentifierEnum::ARS_PARENT_ID => '77777777-7777-7777-7777-0000AAAA1111',
                ],
                'expected' => [
                    'correlationId' => '55555555-5555-5555-5555-0000AAAA1111',
                    'requestId' => '00000000-0000-0000-0000-0000AAAA1111',
                    'parentId' => '66666666-6666-6666-6666-0000AAAA1111',
                ],
            ],
        ];
    }

    private function getMockForUuid(string $uuid): UuidInterface
    {
        $mock = $this->getMockBuilder(UuidInterface::class)->getMock();

        $mock->method('toString')->willReturn($uuid);

        return $mock;
    }

    /**
     * @dataProvider providerCreation
     *
     * @param array $data
     * @param array $expected
     */
    public function testCreation(array $data, array $expected): void
    {
        $manager = new ArsHeaderManager($data, $this->factory);

        self::assertSame($expected['correlationId'], $manager->getCorrelationId(), 'Failed for correlation id');
        self::assertSame($expected['requestId'], $manager->getRequestId(), 'Failed for request id');
        self::assertSame($expected['parentId'], $manager->getParentId(), 'Failed for parent id');
    }

    /**
     * @dataProvider providerCreation
     *
     * @param array $data
     * @param array $expected
     */
    public function testInitFromGlobals(array $data, array $expected): void
    {
        foreach ($data as $key => $value) {
            $_SERVER[$key] = $value;
        }

        $manager = ArsHeaderManager::initFromGlobals($this->factory, true);

        self::assertSame($expected['correlationId'], $manager->getCorrelationId(), 'Failed for correlation id');
        self::assertSame($expected['requestId'], $manager->getRequestId(), 'Failed for request id');
        self::assertSame($expected['parentId'], $manager->getParentId(), 'Failed for parent id');
    }
}
