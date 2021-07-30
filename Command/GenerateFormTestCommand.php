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
use Symfony\Component\Form\Forms;
use \ReflectionClass;
use \ReflectionMethod;

class GenerateFormTestCommand extends ContainerAwareCommand
{
    private $bundle;

    private $regen;

    private $skeletonDirs;

    //constructor

    public function configure()
    {
        $this
            ->setName('generate:test:form')
            ->setDescription('Generates tests for the current forms')
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
        $output->writeln('<info>+---------------------------+');
        $output->writeln('|    Form test generator    |');
        $output->writeln('+---------------------------+</info>');

        $output->writeln(sprintf ('Generating form test files for %s bundle.', $this->bundle));

        $entityLocation = 'src/'. $this->bundle .'/Form';
        $testLocation = 'src/'. $this->bundle .'/Tests/Form';

        //from Symfony\Component\Form\Test\FormIntegrationTestCase
        $formFactory = Forms::createFormFactoryBuilder()
                    ->addExtensions(array())
                    ->addTypeExtensions(array())
                    ->addTypes(array())
                    ->addTypeGuessers(array())
                    ->getFormFactory();

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

            if (!class_exists($this->bundle .'\\Form\\'. $className))
            {
                $output->writeln(sprintf('The file %s was found but the class %s was not found in it', $testLocation .'/'. $file->getFilename(), $this->bundle .'\\'. $className));
                continue;
            }

            $output->writeln(sprintf('Generating test class for %s form class', $className));

            
            //$form = $formFactory->create($this->bundle .'\\Form\\'. $className);
            //$entity = $form->getConfig()->getDataClass();
            //For now just guess the class //TODO: get class name from the form class
            $entityClassName = substr($this->bundle .'\\Entity\\'. $className, 0, -4);
            if (!class_exists($entityClassName))
            {
                $output->writeln(sprintf('The form %s was found but the entity %s was not found.', $className, $entityClassName));
                $content = <<< EOT
<?php
/**
 * Form test template taken from 
 * https://symfony.com/doc/3.4/form/unit_testing.html
 */

namespace $this->bundle\Tests\Form;

use $this->bundle\\Form\\$className;
use $this->bundle\\Entity\\$entityClassName;
use Symfony\Component\Form\Test\TypeTestCase;

/**
 * Autogenerated test class for form {{ form_class }}
 */
class ${className}Test extends TypeTestCase
{
    public function testSubmitValidData()
    {
        \$this->markTestSkipped('No entity matching this form, skipping');
    }

    public function testCustomFormView()
    {
        \$this->markTestSkipped('No entity matching this form, skipping');
    }
}
EOT;

                $output->writeln(sprintf('Rendering file "%s".', $className .'Test.php'));

                file_put_contents($testLocation.'/'. $className .'Test.php', $content);
            
                $output->writeln(sprintf('Rendered file "%s". Test file generated', $className .'Test.php'));
                $output->writeln('-----');
            }
            else
            {
                $entity = new $entityClassName();
                $refClass = new ReflectionClass($entity);
                $props = $refClass->getProperties();
                
                
                //die();
                
                //loop through properties and build data
                $actions= array();
                //var_dump($props);
                foreach ($props as $property)
                {
                    //echo $property->getName();
                    //get docblock and check it exists
                    $docBlock = $property->getDocComment();
                    // if (null == $docBlock)
                    // {
                    //     continue;
                    // }

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
                    //get type property from col data
                    $type = '';
                    $name = '';
                    foreach ($columnData as $data)
                    {
                        $typePos = strpos($data, 'type=');
                        
                        if ($typePos !== false)
                        {
                            //found the type, save it and break out
                            $type = substr($data, $typePos+6);
                            
                        }
                        $namePos = strpos($data, 'name=');
                        if ($namePos !== false)
                        {
                            //found the type, save it and break out
                            $name = substr($data, $namePos+5);
                            
                        }
                    }
                    //kock end quote mark off 
                    $type = substr($type, 0, -1);
                    $name = substr($name, 1, -1);

                    //override type if ID field
                    if (strpos($docBlock, '@ORM\Id'))
                    {
                        continue;
                    }

                    if (null == $type || !in_array($type, ['string', 'integer', 'boolean','Id', 'float', 'text', 'datetime', 'array', 'simple_array']))
                    {
                        $type = 'Unknown';
                    }
                                        
                    echo $name .' => '.$type."\n";
                    $value = $this->getTestValue($type);

                    //prepare the data
                    //$elementData[$name] = $value;

                    //add to actions list
                    //$actions[] = $elementData;
                    $actions[$name] = $value;
                }
                $output->writeln('Found ' . count ($actions) .' properties');

                //var_dump($actions);
                //prepare twig parameters
                $parameters = array(
                    'namespace' => $this->bundle,
                    'bundle' => $this->bundle,
                    'format' => array(
                        'routing' => 'yaml',//$routeFormat,
                        'templating' => 'twig',
                    ),
                    'form_class' => $className,
                    'entity_class'=> $entityClassName,
                    'form_fields'   => $actions,
                );
                //var_dump($parameters);
                $output->writeln(sprintf('Rendering file "%s".', $className .'Test.php'));

                $twig = $this->getTwigEnvironment();

                $content = $twig->render('Form/FormTypeTest.php.twig', $parameters);
                file_put_contents($testLocation.'/'. $className .'Test.php', $content);
            
                $output->writeln(sprintf('Rendered file "%s". Test file generated', $className .'Test.php'));
                $output->writeln('-----');
            }
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

    private function getTestValue($type)
    {
        $return = null;
        switch ($type) {
            case 'string':
                $return = '"This is a test string and should look like this"';
                break;  
            case 'integer':
                $return = '12345';
                break;
            case 'boolean':
                $return = 'true';
                break;
            case 'Id':
                $return = '1234';
                break;
            case 'text':
                $return = <<< EOT
"This is a test text entry. As you can see it has multiple lines and is
much longer than the string type. But it essentially has the same kind
of data and the same kind of tests."
EOT;
                break;
            case 'float':
                $return = '123.45';
                break;
            case 'array':
            case 'simple_array':
                $return = 'array(\'first\' => \'1st\')';
                break;
            case 'datetime':
                $return = 'new \\DateTime(\'21-11-2019 20:05\')';
                break;
            default:
                $return = 'null';
                break;
        }
        return $return;
    }
}