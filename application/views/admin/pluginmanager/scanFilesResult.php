<div class='col-lg-12'>
    <div class='pagetitle h3'><?php eT('Plugin manager - scanned files'); ?></div>
    <div class='col-sm-12'>
            <div class='pull-right'>
                <a 
                    href='<?php echo $scanFilesUrl; ?>'
                    class='btn btn-default'
                    data-toggle='tooltip'
                    title='<?php eT('Scan files for available plugins'); ?>'
                >
                    <i class='fa fa-file '></i>
                    <i class='fa fa-search '></i>&nbsp;
                    <?php eT('Scan files'); ?>
                </a>
                &nbsp;
            </div>
        </div>
    <?php foreach($result as $name => $scannedPlugin): ?>
        <div class='form-group col-lg-12'>
            <label class='control-label col-sm-4'>
                <?php echo $name; ?>
            </label>
            <?php if ($scannedPlugin['load_error'] == 0 && $scannedPlugin['extensionConfig'] == null): ?>
                <i class='fa fa-ban text-warning'></i>&nbsp;
                <span class='text-warning'><?php eT('Missing configuration file.'); ?></span>
            <?php elseif ($scannedPlugin['isCompatible']): ?>
                <?php echo CHtml::beginForm($installUrl, 'post', ['style' => 'display: inline-block;']); ?>
                    <input type='hidden' name='pluginName' value='<?php echo $name; ?>' />
                    <button href='' class='btn btn-success' data-toggle='tooltip' title='<?php eT('Install this plugin'); ?>'>
                        <i class='fa fa-download'></i>&nbsp;
                        <?php eT('Install'); ?>
                    </button>
                </form>
            <?php elseif ($scannedPlugin['load_error'] == 0
                          && $scannedPlugin['extensionConfig'] != null
                          && !$scannedPlugin['isCompatible']): ?>
                <i class='fa fa-ban text-warning'></i>&nbsp;
                <span class='text-warning'><?php eT('Plugin is not compatible with your LimeSurvey version.'); ?></span>
            <?php else: ?>
                <i class='fa fa-exclamation-triangle text-warning'></i>&nbsp;
                <span class='text-warning'><?php eT('Load error. Please contact the plugin author.'); ?></span>
            <?php endif; ?>

            <?php if (isset($scannedPlugin['deleteUrl'])): ?>
                <a href='#' class='btn btn-danger' data-target='#confirmation-modal' data-toggle='modal' data-href='<?=$scannedPlugin['deleteUrl']?>' data-message='<?php eT('Are you sure you want to delete this plugin from the file system?'); ?>' type='submit'>
                    <i class='fa fa-trash'></i>&nbsp;
                    <span data-toggle='tooltip' title='<?php eT('Delete this plugin from the file system'); ?>'>Delete files</span>
                </a>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
