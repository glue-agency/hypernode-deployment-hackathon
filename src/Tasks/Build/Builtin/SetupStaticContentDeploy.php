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
     * @param string $area
     * @param string $theme
     * @param array $languages
     *
     * @return ArrayInput
     */
    protected function getStaticContentDeployArrayInput(
        $area = '',
        $theme = '',
        array $languages = []
    ): ArrayInput {
        $parameters = [];
        $parameters['command'] = self::CMD_NAME_STATIC_CONTENT_DEPLOY;
        $parameters['--' . DeployStaticOptions::FORCE_RUN] = true;

        //$parameters['--' . DeployStaticOptions::EXCLUDE_AREA] = [''];

        if ($area != '') {
            $parameters['--' . DeployStaticOptions::AREA] = [$area];
        }

        if ($theme != '') {
            $parameters['--' . DeployStaticOptions::THEME] = [$theme];
        }

        if (count($languages) > 0) {
            $parameters[DeployStaticOptions::LANGUAGES_ARGUMENT] = $languages;
        }

        if (isset($this->config['static-content']['jobs'])) {
            $parameters['--' . DeployStaticOptions::JOBS_AMOUNT] = $this->config['static-content']['jobs'];
        }

        return new ArrayInput($parameters);
    }

    protected function runStaticDeploys(){
        $actions['frontend'] = $this->config['static-content']['frontend'];
        $actions['adminhtml'] = $this->config['static-content']['adminhtml'];
        foreach($actions as $area => $themes){
            foreach($themes as $theme => $languages){
                $this->environment->logMessage('Start deploy for: '.$area.' '.$theme.' '.implode(' ',$languages));
                $this->environment->log(
                    $this->runCommand(
                        $this->getStaticContentDeployArrayInput($area,$theme,$languages)
                    )->fetch()
                );
            }
        }
    }

    protected function getConfigValues(){
        if($this->config === null){
            $this->config = $this->environment->getConfig();
        }
    }
}
