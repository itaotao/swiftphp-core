<?php

namespace SwiftPHP\Scaffold\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputOption;

class MakeController extends BaseCommand
{
    protected $commandName = 'make:controller';

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setName($this->commandName)
            ->setDescription('Create a new controller class')
            ->addArgument('name', InputArgument::REQUIRED, 'The controller name (e.g., User)')
            ->addOption('rest', 'r', InputOption::VALUE_NONE, 'Create a RESTful controller')
            ->addOption('api', 'a', InputOption::VALUE_NONE, 'Create an API controller');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');
        $isRest = $input->getOption('rest');
        $isApi = $input->getOption('api');

        if ($this->validateName($name, $io)) {
            return Command::FAILURE;
        }

        $className = $this->toPascalCase($name);
        $directory = $this->buildPath($this->basePath, 'app', 'controller');
        $this->ensureDirectory($directory);

        $filePath = $this->buildPath($directory, $className . 'Controller.php');

        if (file_exists($filePath)) {
            $io->error("Controller {$className}Controller already exists!");
            return Command::FAILURE;
        }

        $namespace = 'App\\Controller';
        $extends = $isApi ? 'Controller' : 'Controller';
        $content = $this->generateContent($namespace, $className, $extends, $isRest, $isApi);

        if (file_put_contents($filePath, $content) === false) {
            $io->error("Failed to create controller file");
            return Command::FAILURE;
        }

        $io->success("Controller {$className}Controller created successfully!");
        $io->text("Path: {$filePath}");

        return Command::SUCCESS;
    }

    protected function generateContent(string $namespace, string $className, string $extends, bool $isRest, bool $isApi): string
    {
        $useStatements = "use SwiftPHP\\Controller\\Controller;\nuse SwiftPHP\\Request\\Request;\nuse SwiftPHP\\Response\\Response;";
        $extendsClass = "extends {$extends}";

        if ($isRest) {
            $methods = $this->generateRestMethods();
        } elseif ($isApi) {
            $methods = $this->generateApiMethods();
        } else {
            $methods = $this->generateBasicMethods();
        }

        return <<<PHP
<?php

namespace {$namespace};

{$useStatements}

class {$className}Controller {$extendsClass}
{
{$methods}
}
PHP;
    }

    protected function generateBasicMethods(): string
    {
        return <<<'PHP'
    public function index(?Request $request = null): Response
    {
        return $this->json([
            'code' => 200,
            'msg' => 'success',
            'data' => []
        ]);
    }

    public function show(?Request $request = null): Response
    {
        $id = $request->param('id', 0);
        return $this->json([
            'code' => 200,
            'msg' => 'success',
            'data' => ['id' => $id]
        ]);
    }

    public function create(?Request $request = null): Response
    {
        return $this->json([
            'code' => 200,
            'msg' => 'success'
        ]);
    }

    public function save(?Request $request = null): Response
    {
        $data = $request->post();
        return $this->json([
            'code' => 200,
            'msg' => 'created successfully',
            'data' => $data
        ]);
    }

    public function edit(?Request $request = null): Response
    {
        $id = $request->param('id', 0);
        return $this->json([
            'code' => 200,
            'msg' => 'success',
            'data' => ['id' => $id]
        ]);
    }

    public function update(?Request $request = null): Response
    {
        $id = $request->param('id', 0);
        $data = $request->post();
        return $this->json([
            'code' => 200,
            'msg' => 'updated successfully',
            'data' => ['id' => $id, 'data' => $data]
        ]);
    }

    public function delete(?Request $request = null): Response
    {
        $id = $request->param('id', 0);
        return $this->json([
            'code' => 200,
            'msg' => 'deleted successfully'
        ]);
    }
PHP;
    }

    protected function generateRestMethods(): string
    {
        return <<<'PHP'
    public function index(?Request $request = null): Response
    {
        return $this->json([
            'code' => 200,
            'msg' => 'success',
            'data' => []
        ]);
    }

    public function store(?Request $request = null): Response
    {
        $data = $request->post();
        return Response::json([
            'code' => 201,
            'msg' => 'Resource created',
            'data' => $data
        ], 201);
    }

    public function show(?Request $request = null): Response
    {
        $id = $request->param('id', 0);
        return $this->json([
            'code' => 200,
            'msg' => 'success',
            'data' => ['id' => $id]
        ]);
    }

    public function update(?Request $request = null): Response
    {
        $id = $request->param('id', 0);
        $data = $request->post();
        return $this->json([
            'code' => 200,
            'msg' => 'Resource updated',
            'data' => ['id' => $id, 'data' => $data]
        ]);
    }

    public function destroy(?Request $request = null): Response
    {
        $id = $request->param('id', 0);
        return $this->json([
            'code' => 200,
            'msg' => 'Resource deleted'
        ]);
    }
PHP;
    }

    protected function generateApiMethods(): string
    {
        return <<<'PHP'
    public function index(?Request $request = null): Response
    {
        $page = $request->param('page', 1);
        $limit = $request->param('limit', 15);
        return $this->json([
            'code' => 200,
            'msg' => 'success',
            'data' => [
                'list' => [],
                'total' => 0,
                'page' => $page,
                'limit' => $limit
            ]
        ]);
    }

    public function show(?Request $request = null): Response
    {
        $id = $request->param('id', 0);
        return $this->json([
            'code' => 200,
            'msg' => 'success',
            'data' => ['id' => $id]
        ]);
    }

    public function store(?Request $request = null): Response
    {
        $data = $request->post();
        $validate = new \SwiftPHP\Validate\Validate();
        if (!$validate->check($data)) {
            return Response::json([
                'code' => 422,
                'msg' => 'Validation failed',
                'errors' => $validate->getErrors()
            ], 422);
        }
        return Response::json([
            'code' => 201,
            'msg' => 'Created successfully',
            'data' => $data
        ], 201);
    }

    public function update(?Request $request = null): Response
    {
        $id = $request->param('id', 0);
        $data = $request->post();
        return $this->json([
            'code' => 200,
            'msg' => 'Updated successfully',
            'data' => ['id' => $id]
        ]);
    }

    public function destroy(?Request $request = null): Response
    {
        $id = $request->param('id', 0);
        return $this->json([
            'code' => 200,
            'msg' => 'Deleted successfully'
        ]);
    }
PHP;
    }
}