<?php
namespace InfluxDB\Integration;

use DateTime;
use DateTimeZone;
use InfluxDB\Options;
use InfluxDB\Adapter\UdpAdapter;
use InfluxDB\Adapter\GuzzleAdapter as InfluxHttpAdapter;
use GuzzleHttp\Client as GuzzleHttpClient;
use InfluxDB\Client;
use InfluxDB\Integration\Framework\TestCase;
use InfluxDB\Adapter\Udp\Writer as UdpWriter;
use InfluxDB\Adapter\Http\Writer;
use InfluxDB\Adapter\Http\Reader;

class ClientTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->getClient()->createDatabase("tcp.test");
        $this->getClient()->createDatabase("udp.test");
    }

    public function testSimpleMarkPublicSignature()
    {
        $options = new Options();
        $options->setDatabase("tcp.test");

        $guzzleHttp = new GuzzleHttpClient();
        $writer = new Writer($guzzleHttp, $options);
        $reader = new Reader($guzzleHttp, $options);
        $client = new Client($reader, $writer);

        $client->mark("vm", ["mark" => "element"]);

        $this->assertSerieExists("tcp.test", "vm");
        $this->assertSerieCount("tcp.test", "vm", 1);
        $this->assertValueExistsInSerie("tcp.test", "vm", "mark", "element");
    }

    public function testDirectMessagesMarkPublicSignature()
    {
        $options = new Options();
        $options->setDatabase("tcp.test");

        $guzzleHttp = new GuzzleHttpClient();
        $writer = new Writer($guzzleHttp, $options);
        $reader = new Reader($guzzleHttp, $options);
        $client = new Client($reader, $writer);

        $client->mark([
            "database" => "tcp.test",
            "retentionPolicy" => "default",
            "points" => [
                [
                    "measurement" => "tt",
                    "fields" => [
                        "cpu" => 1,
                        "mem" => 2,
                    ],
                ]
            ],
        ]);

        $this->assertSerieExists("tcp.test", "tt");
        $this->assertSerieCount("tcp.test", "tt", 1);
        $this->assertValueExistsInSerie("tcp.test", "tt", "cpu", 1);
        $this->assertValueExistsInSerie("tcp.test", "tt", "mem", 2);
    }

    public function testListActiveDatabases()
    {
        $options = new Options();
        $guzzleHttp = new GuzzleHttpClient();
        $writer = new Writer($guzzleHttp, $options);
        $reader = new Reader($guzzleHttp, $options);
        $client = new Client($reader, $writer);

        $databases = $client->getDatabases();

        $this->assertCount(2, $databases["results"][0]["series"][0]["values"]);
    }

    public function testCreateANewDatabase()
    {
        $options = new Options();
        $guzzleHttp = new GuzzleHttpClient();
        $writer = new Writer($guzzleHttp, $options);
        $reader = new Reader($guzzleHttp, $options);
        $client = new Client($reader, $writer);

        $client->createDatabase("walter");

        $databases = $client->getDatabases();

        $this->assertCount(3, $databases["results"][0]["series"][0]["values"]);
    }

    public function testDropExistingDatabase()
    {
        $options = new Options();
        $guzzleHttp = new GuzzleHttpClient();
        $writer = new Writer($guzzleHttp, $options);
        $reader = new Reader($guzzleHttp, $options);
        $client = new Client($reader, $writer);

        $client->createDatabase("walter");
        $this->assertDatabasesCount(3);

        $client->deleteDatabase("walter");
        $this->assertDatabasesCount(2);
    }

    /**
     * Test that we handle socket problems correctly in the UDP
     * adapter, and that they don't inturrupt the user's application.
     *
     * @group udp
     */
    public function testReplicateIssue27()
    {
        $options = new \InfluxDB\Options();

        // Configure options
        $options->setHost('172.16.1.182');
        $options->setPort(4444);
        $options->setDatabase('...');
        $options->setUsername('root');
        $options->setPassword('root');

        $guzzleHttp = new GuzzleHttpClient();
        $writer = new UdpWriter($options);
        $reader = new Reader($guzzleHttp, $options);
        $client = new Client($reader, $writer);
        $client->mark("udp.test", ["mark" => "element"]);
    }

    /**
     * @group udp
     */
    public function testWriteUDPPackagesToNoOne()
    {
        $options = new Options();
        $options->setHost("127.0.0.1");
        $options->setUsername("nothing");
        $options->setPassword("nothing");
        $options->setPort(64071); //This is a wrong port

        $guzzleHttp = new GuzzleHttpClient();
        $writer = new UdpWriter($options);
        $reader = new Reader($guzzleHttp, $options);
        $client = new Client($reader, $writer);

        $client->mark("udp.test", ["mark" => "element"]);
    }

    /**
     * @group udp
     */
    public function testWriteUDPPackagesToInvalidHostname()
    {
        $options = new Options();
        $options->setHost("www.test-invalid.this-is-not-a-tld");
        $options->setUsername("nothing");
        $options->setPassword("nothing");
        $options->setPort(15984);

        $guzzleHttp = new GuzzleHttpClient();
        $writer = new UdpWriter($options);
        $reader = new Reader($guzzleHttp, $options);
        $client = new Client($reader, $writer);

        $client->mark("udp.test", ["mark" => "element"]);
    }
}
