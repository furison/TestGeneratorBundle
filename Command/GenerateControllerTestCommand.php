<?php

/**
 * Symfony test generator
 * (c) 2020 Alex Antrobus
 */

namespace Furison\TestGeneratorBundle\Command;
//use statements
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use \ReflectionClass;
use \ReflectionMethod;

class GenerateControllerTestCommand extends ContainerAwareCommand
{
    private $bundle;

    private $skeletonDirs;

    //constructor

    public function configure()
    {
        $this
            ->setName('generate:test:controller')
            ->setDescription('Generates tests for the current controllers')
            ->setDefinition(array(
                new InputArgument('bundle', InputArgument::REQUIRED, 'The bundle where the command is generated'),
            ));
    }

    public function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->bundle = $input->getArgument('bundle');
        $this->skeletonDirs = 'src\\Furison\\TestGeneratorBundle\\Resources\\skeleton';
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>+-------------------------------+');
        $output->writeln('|   Controller test generator   |');
        $output->writeln('+-------------------------------+</info>');

        $output->writeln(sprintf ('Generating controller test files for %s bundle.', $this->bundle));

        $controllerLocation = 'src/'. $this->bundle .'/Controller';
        $testLocation = 'src/'. $this->bundle .'/Tests/Controller';

        // find controller files
        $finder = new Finder();
        $finder->files()->name('*Controller.php')->in($controllerLocation);

        $filesystem = new Filesystem();

        foreach ($finder as $file)
        {
            //get class name from filename
            $className = substr($file->getFilename(), 0, -4);

            if ($filesystem->exists($testLocation .'/'. $className .'Test.php'))
            {
                $output->writeln(sprintf('<fg=yellow>Test file %s already exists, skipping...</fg=yellow>', $testLocation .'/'. $className .'Test.php'));
                continue;
            }

            
            if (!class_exists($this->bundle .'\\Controller\\'. $className))
            {
                $output->writeln(sprintf('File %s was found but the class %s was not found in it', $testLocation .'/'. $file->getFilename(), $className));
                continue;
            }

            $output->writeln(sprintf('Generating test class for %s controller class', $className));

            //get a reflection class so we can find the methods of the original class
            $refClass = new ReflectionClass($this->bundle .'\\Controller\\'. $className);
            $methods = $refClass->getMethods(ReflectionMethod::IS_PUBLIC);

            //loop through the public functions and build data
            $actions = array();
            foreach ($methods as $method)
            {
                //get docblock and check it exists
                $docBlock = $method->getDocComment();
                if (null == $docBlock)
                {
                    continue;
                }

                //check @Route annotation exists
                $routePos = strpos($docBlock, '@Route("');
                if (false === $routePos)
                {
                    //we don't have a pos, so lets ignore this one
                    continue;
                }
                //get route url part
                $route = substr($docBlock, $routePos+8);
                $route = substr($route, 0, strpos($route, '"'));

                //add url to action data
                $data['route'] = $route;

                //get the name of the function
                $data['basename'] = ucfirst($method->getName());

                //add to actions list
                $actions[] = $data;
            }
            $output->writeln('Found ' . count ($actions) .' actions');

            //prepare twig parameters
            $parameters = array(
                'namespace' => $this->bundle .'\\Controller',
                'bundle' => $this->bundle,
                'format' => array(
                    'routing' => 'yaml',//$routeFormat,
                    'templating' => 'twig',
                ),
                'controller' => substr($className, 0, -10),//$controller,
                'actions'   => $actions,
            );
            //var_dump($parameters);
            $output->writeln(sprintf('Rendering file "%s".', $className .'Test.php'));

            //use the twig skeleton to create a test class file
            $twig = $this->getTwigEnvironment();
            $content = $twig->render('controller/ControllerTest.php.twig', $parameters);
            file_put_contents($testLocation.'/'. $className .'Test.php', $content);
           
            $output->writeln(sprintf('<info>Rendered file "%s". Test for %s generated</info>', $className .'Test.php', $className));
            $output->writeln('-----------------');
        }
        $output->writeln('<info>Done!');
        $output->writeln('Generated test files for bundle '. $this->bundle .'</info>');
    }

    protected function getTwigEnvironment()
    {
        return new \Twig\Environment(new \Twig\Loader\FilesystemLoader($this->skeletonDirs), array(
            'debug' => true,
            'cache' => false,
            'strict_variables' => true,
            'autoescape' => false,
        ));
    }
}