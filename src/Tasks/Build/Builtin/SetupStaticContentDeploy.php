<?php

namespace Hypernode\Deployment\Tasks\Build\Builtin;

use Error;
use Exception;
use Hypernode\Deployment\Assets\AssetMover;
use Magento\Deploy\Console\DeployStaticOptions;
use Hypernode\Deployment\Tasks\Task\AbstractTask;
use Magento\Framework\App\Area;
use Symfony\Component\Console\Input\ArrayInput;

class SetupStaticContentDeploy extends AbstractTask
{
    const CMD_NAME_STATIC_CONTENT_DEPLOY = 'setup:static-content:deploy';

    protected $config;

    /**
     * @return void
     */
    public function run()
    {
        // TODO remove files first?

        try {
            $this->environment->log('Deployment mode: '.$this->getDeploymentMode());

            if(!$this->isProdMode()){
                $this->environment->log('Skip executing static content deploy when not in PROD mode...');
                return;
            }

            $this->environment->log('Executing static content deploy...');

            $this->getConfigValues();

            $this->runStaticDeploys();
        } catch (Error $e) {
            $this->environment->getLogger()->error($e->getMessage());

            return;
        } catch (Exception $e) {
            $this->environment->getLogger()->error($e->getMessage());

            return;
        }

        try {
            $this->environment->log(sprintf('Moving compiled assets to %s', $this->environment->getStaticDir()));
            // TODO change?
            AssetMover::moveAssetDirectory(
                $this->environment->getProjectRoot() . $this->environment->getStaticDir(),
                $this->environment->getProjectRoot() . $this->environment->getStaticDirInit()
            );
            $this->environment->log('Done moving compiled assets');
        } catch (Error $e) {
            $this->environment->getLogger()->error($e->getMessage());
        } catch (Exception $e) {
            $this->environment->getLogger()->error($e->getMessage());
        }
    }


    /**
     * @param array $areas
     * @param array $themes
     * @param array $languages
     *
     * @return ArrayInput
     */
    protected function getStaticContentDeployArrayInput(
        $areas = [],
        $themes = [],
        array $languages = []
    ): ArrayInput {
        $parameters = [];

        $parameters['command'] = self::CMD_NAME_STATIC_CONTENT_DEPLOY;

        $parameters['--' . DeployStaticOptions::FORCE_RUN] = true;

        $parameters['--' . DeployStaticOptions::STRATEGY] = $this->config['static-content']['strategy'];

        if (count($areas) > 0) {
            $parameters['--' . DeployStaticOptions::AREA] = $areas;
        }

        if (count($themes) > 0) {
            $parameters['--' . DeployStaticOptions::THEME] = $themes;
        }

        if (count($languages) > 0) {
            $parameters[DeployStaticOptions::LANGUAGES_ARGUMENT] = $languages;
        }

        if (isset($this->config['static-content']['jobs'])) {
            $parameters['--' . DeployStaticOptions::JOBS_AMOUNT] = $this->config['static-content']['jobs'];
        }

        $this->environment->logMessage('Deploy parameters: ' . json_encode($parameters));

        return new ArrayInput($parameters);
    }

    protected function runStaticDeploys(){
        $areas = ['adminhtml','frontend'];

        foreach($areas as $area){

            $themes = array_keys($this->config['static-content'][$area]);
            foreach($themes as $theme){
                $langs = $this->config['static-content'][$area][$theme];
                $input = $this->getStaticContentDeployArrayInput([$area],[$theme],$langs);
                $this->runStaticDeploy($input);
            }

        }
    }

    protected function runStaticDeploy($inputParams){
        $this->environment->logMessage('Start static file deploy ... ');
        $this->environment->log(
            $this->runCommand(
                $inputParams
            )->fetch()
        );
    }

    protected function getConfigValues(){
        if($this->config === null){
            $this->config = $this->environment->getConfig();
        }
    }
}
