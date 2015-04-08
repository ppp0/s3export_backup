<?php

class ImportExportClientTest extends \Guzzle\Tests\GuzzleTestCase {

    protected $client;

    public function setUp()
    {
        $this->client = $this->getServiceBuilder()->get('importexport');
    }


    public function testGetPrepaidLabel() {
        $apiClient = Aws\ImportExport\ImportExportClient::factory(array('key' => 'AKIAIU6SVPAPN7XU2LQQ', 'secret' => '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',));

        var_dump ($this->client->getCommand('CreateJob', array(
            'JobType'      => 'IMPORT',
            'ValidateOnly' => true,
            'Manifest'     => array(
                'foo' => 'bar',
                'bar' => array('foo', 'bar', 'baz'),
                'baz' => 'foo',
            ),
        )));
        //$this->assertInstanceOf('Aws\Common\Command\QueryCommand', $apiClient->getShippingLabel(['jobIds' => ['79YAC']]));
    }
}
