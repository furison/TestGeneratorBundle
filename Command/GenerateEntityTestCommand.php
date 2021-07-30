<?php

/**
 * Symfony test generator
 * (c) 2020 Alex Antrobus
 */

namespace Furison\TestGeneratorBundle\Command;
//use statements
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use \ReflectionClass;
use \ReflectionMethod;

class GenerateEntityTestCommand extends ContainerAwareCommand
{
    private $bundle;

    private $regen;

    private $skeletonDirs;

    //constructor

    public function configure()
    {
        $this
            ->setName('generate:test:entity')
            ->setDescription('Generates tests for the current entities')
            ->setDefinition(array(
                new InputArgument('bundle', InputArgument::REQUIRED, 'The bundle where the command is generated'),
                new InputOption('regen', null, InputOption::VALUE_NONE, 'Whether to regenerate the enitity test files'),
            ));
    }

    public function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->bundle = $input->getArgument('bundle');
        $this->regen = $input->getOption('regen');
        $this->skeletonDirs = 'src\\Furison\\TestGeneratorBundle\\Resources\\skeleton';
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>+-------------------------------+');
        $output->writeln('|     Entity test generator     |');
        $output->writeln('+-------------------------------+</info>');

        $output->writeln(sprintf ('Generating entity test files for %s bundle.', $this->bundle));

        $entityLocation = 'src/'. $this->bundle .'/Entity';
        $testLocation = 'src/'. $this->bundle .'/Tests/Entity';

        // find entity files
        $finder = new Finder();
        $finder->files()->name('*.php')->in($entityLocation);

        $filesystem = new Filesystem();

        if (!is_dir($testLocation))
        {
            $output->writeln(sprintf('The directory "%s" does not exist, creating...', $testLocation));
            $filesystem->mkdir($testLocation);
            $output->writeln('OK');
        }

        foreach ($finder as $file)
        {
            $className = substr($file->getFilename(), 0, -4);

            if (!$this->regen && $filesystem->exists($testLocation .'/'. $className .'Test.php'))
            {
                $output->writeln(sprintf('Test file %s already exists, skipping...', $testLocation .'/'. $className .'Test.php'));
                continue;
            }

            if (!class_exists($this->bundle .'\\Entity\\'. $className))
            {
                $output->writeln(sprintf('The file %s was found but the class %s was not found in it', $testLocation .'/'. $file->getFilename(), $this->bundle .'\\'. $className));
                continue;
            }

            $output->writeln(sprintf('Generating test class for %s entity class', $className));

            $refClass = new ReflectionClass($this->bundle .'\\Entity\\'. $className);
            $props = $refClass->getProperties();

            //loop through properties and build data
            $actions= array();
            foreach ($props as $property)
            {
                //get docblock and check it exists
                $docBlock = $property->getDocComment();
                if (null == $docBlock)
                {
                    continue;
                }

                //check the Column annotation exists
                $routePos = strpos($docBlock, '@ORM\Column(');
                if (false === $routePos)
                {
                    //we don't have a pos, so lets ignore this one
                    continue;
                }
                //get the Column data
                $colData = substr($docBlock, $routePos+12);
                $colData = substr($colData, 0, strpos($colData, ')'));
                $columnData = explode(',', $colData);

                //get type property from col data
                $type = '';
                foreach ($columnData as $data)
                {
                    $typePos = strpos($data, 'type=');
                    if ($typePos !== false)
                    {
                        //found the type, save it and break out
                        $type = substr($data, $typePos+6);
                        break;
                    }
                }
                //kock end quote mark off
                $type = substr($type, 0, -1);

                //override type if ID field
                if (strpos($docBlock, '@ORM\Id'))
                {
                    $type = 'Id';
                }

                if (null == $type || !in_array($type, ['string', 'integer', 'boolean','Id', 'float', 'text', 'datetime', 'date', 'array']))
                {
                    $type = 'Unknown';
                }

                //prepare the data
                $actionData['type'] = $type;
                $actionData['name'] = $property->name;

                //add to actions list
                $actions[] = $actionData;
            }
            $output->writeln('Found ' . count ($actions) .' properties');

            //prepare twig parameters
            $parameters = array(
                'namespace' => $this->bundle .'\\Entity',
                'bundle' => $this->bundle,
                'format' => array(
                    'routing' => 'yaml',//$routeFormat,
                    'templating' => 'twig',
                ),
                'class' => $className,
                'actions'   => $actions,
            );
            //var_dump($parameters);
            $output->writeln(sprintf('Rendering file "%s".', $className .'Test.php'));

            $twig = $this->getTwigEnvironment();

            $content = $twig->render('Entity/EntityTest.php.twig', $parameters);
            file_put_contents($testLocation.'/'. $className .'Test.php', $content);

            $output->writeln(sprintf('Rendered file "%s". Test file generated', $className .'Test.php'));
            $output->writeln('-----');
        }
        $output->writeln('Generated test files for bundle '. $this->bundle);
    }

    protected function getTwigEnvironment()
    {
        return new \Twig_Environment(new \Twig_Loader_Filesystem($this->skeletonDirs), array(
            'debug' => true,
            'cache' => false,
            'strict_variables' => true,
            'autoescape' => false,
        ));
    }
}
