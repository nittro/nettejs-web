<?php

namespace App\Presenters;


use App\Libs\Builder;
use App\Libs\FormRules;
use Nette\Application\Responses\FileResponse;
use Nette\Application\UI\Form;
use Nette\FileNotFoundException;


class HomepagePresenter extends BasePresenter {

    private $packageInfo = [
        'base' => [
            'forms' => 'Handles form validation and submission via AJAX. Note that selecting this package will include <code>netteForms.js</code> automatically.',
        ],
        'extras' => [
            'checklist' => 'Drag to toggle consecutive checkboxes. You can try it here!',
            'dialogs' => 'Open any snippet in a dialog',
            'confirm' => 'Replace that ugly <code>window.confirm()</code> with something nicer',
            'dropzone' => 'Drag &amp; drop file upload has never been easier',
            'paginator' => 'Replace that ugly row of numbers below your content with infinite scrolling!',
            'keymap' => 'Centralised key binding manager',
            'storage' => 'A nicer API for DOM Storage',
        ],
    ];


    /** @var Builder */
    private $builder;


    public function injectHomepage(Builder $builder) {
        $this->builder = $builder;
    }

    public function actionBuild($id) {
        try {
            $archivePath = $this->builder->getArchivePath($id);

            $this->sendResponse(new FileResponse(
                $archivePath,
                sprintf('nittro-custom-%s.zip', date('Y-m-d--H-i-s')),
                'application/zip',
                true
            ));
        } catch (FileNotFoundException $e) {
            $this->error();
        }
    }

    public function renderDefault($tut = null) {
        if ($tut) {
            $this->postGet('this', ['tut' => null]);
            $this->redrawControl('tutorial');

        }
    }

    public function renderDownload() {
        $this->title = 'Download';
        $this->tab = 'download';
        $this->template->builds = $this->context->parameters['builds'];
        $this->template->dependencies = $this->builder->getDependencies();
        $this->template->packageInfo = $this->packageInfo;
    }

    public function renderTutorial($step) {
        $this->setView('tutorial' . (int) $step);

        if ($this->isAjax()) {
            $this->redrawControl('tutorial');
        }
    }


    public function doBuild(Form $form, array $values) {
        try {
            $buildId = $this->builder->build($values);
        } catch (\Exception $e) {
            $form->addError(get_class($e) . ': ' . $e->getMessage());
            return;
        }

        $this->postGet('build', ['id' => $buildId]);
        $this->redrawControl('buildResult');
        $this->template->buildId = $buildId;
    }

    public function doRedrawForm() {
        $this->redrawControl('content');
    }

    public function createComponentBuilderForm() {
        $form = new Form();

        $vendor = $form->addContainer('vendor');
        $vendor->addMultiUpload('js', 'JavaScripts:');
        $vendor->addMultiUpload('css', 'Stylesheets:');

        $form->addCheckboxList('base', 'Base packages:', [
            'core' => 'Core',
            'datetime' => 'DateTime',
            'neon' => 'Neon',
            'di' => 'DI',
            'ajax' => 'AJAX',
            'forms' => 'Forms',
            'page' => 'Page',
            'flashes' => 'Flashes',
            'routing' => 'Routing',
        ]);

        $form->addCheckboxList('extras', 'Extras:', [
            'checklist' => 'CheckList',
            'dialogs' => 'Dialogs',
            'confirm' => 'Confirm',
            'dropzone' => 'DropZone',
            'paginator' => 'Paginator',
            'keymap' => 'KeyMap',
            'storage' => 'Storage',
        ]);

        $libraries = $form->addContainer('libraries');
        $libraries->addMultiUpload('js', 'JavaScripts:');
        $libraries->addMultiUpload('css', 'Stylesheets:');

        $form->addRadioList('bootstrap', 'Bootstrap:', [
                'auto' => 'Generate automatically',
                'custom' => 'Upload custom',
                'none' => 'None',
            ])
            ->setRequired()
            ->addConditionOn($form['base'], FormRules::DOES_NOT_CONTAIN, 'di')
            ->addRule(Form::NOT_EQUAL, 'You must include the DI Base package to use an auto-generated bootstrap', 'auto');

        $form->addTextArea('bootstrap_options', 'Bootstrap options:');

        $form->addUpload('custom_bootstrap', 'Custom bootstrap:')
            ->addConditionOn($form['bootstrap'], Form::EQUAL, 'custom')
            ->addRule(Form::FILLED, 'Please select your custom bootstrap file or choose a different bootstrap option above');

        $form->addCheckbox('stack', 'Include _stack library')
            ->setRequired(false)
            ->addConditionOn($form['base'], FormRules::DOES_NOT_CONTAIN, 'core')
            ->addRule(Form::EQUAL, 'You must include the Core Base package to use the _stack library', false);

        $form->addSubmit('build', 'Build');

        $form->onSuccess[] = [$this, 'doBuild'];
        $form->onError[] = [$this, 'doRedrawForm'];

        $form->setDefaults([
            'base' => [
                'core',
                'datetime',
                'neon',
                'di',
                'ajax',
                'forms',
                'page',
                'flashes',
            ],
            'extras' => [],
            'bootstrap' => 'auto',
            'bootstrap_options' => "params:\nextensions:\nservices:\nfactories:\n",
            'stack' => true,
        ]);

        return $form;
    }
}
