<?php

namespace QPautrat\JingooScraper;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

use GuzzleHttp\Client;

/**
 * @author Quentin Pautrat <quentin.pautrat@gmail.com>
 */
class ScrapCommand extends Command
{
    const BASE_URI          = 'http://www.jingoo.com/';
    const PROXY_BASE_URI    = 'http://proxy.jingoo.com/';

    protected $fs;

    protected $client;

    public function __construct(Filesystem $fs, Client $client)
    {
        $this->fs = $fs;
        $this->client = $client;

        parent::__construct();
    }

    public function configure()
    {
        $this
            ->setName('scrap')
            ->setDescription('Scrap all pictures from all album by given logi nand password')
            ->addArgument('login', InputArgument::REQUIRED, 'Your login')
            ->addArgument('password', InputArgument::REQUIRED, 'Your password')
            ->addOption('destination', 'dest', InputOption::VALUE_OPTIONAL, 'Destination directory to save your pictures', 'pics')
        ;

    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('begin');

        $picsDir = $input->getOption('destination');

        $login = $input->getArgument('login');
        $password = $input->getArgument('password');

        $response = $this->client->request('POST', '/index.php', [
            'form_params' => [
                'login' => $login,
                'password' => $password,
                'action' => 'login'
            ]
        ]);

        $content = $response->getBody()->getContents();

        preg_match_all('/"([\d]+)"/', $content, $matches);

        $albumIds = $matches[1];

        $output->writeln(count($albumIds) . ' albums found');

        $photosCount = 0;

        foreach ($albumIds as $id) {

            $dir = $picsDir . '/' . $id;

            $this->fs->mkdir($dir);

            $response = $this->client->get('/javascripts/liste_photos/liste_photos_album.php?id_album=' . $id . '&_=' . time());

            $photos = json_decode($response->getBody()->getContents());

            $output->writeln('Scraping from album ' . $id . ' : ' . count($photos->listePhoto) . ' pictures found');

            foreach ($photos->listePhoto as $photo) {

                $output->writeln('Scraping picture ' . $photo->id_photo);

                $filename = substr($photo->chemin, strrpos($photo->chemin, '/') + 1);

                if (file_put_contents($dir . '/' . $filename, file_get_contents(self::PROXY_BASE_URI . 'medium/' . $photo->chemin))) {
                    $photosCount++;
                }
            }
        }

        $output->writeln($photosCount . 'pictures scraped !');
    }
}