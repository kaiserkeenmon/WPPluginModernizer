<?php

/**
 * Project: WPPluginModernizer
 * File: Activation.php
 * Author: Kaiser Keenmon
 * Date: 4/1/24
 */

namespace WPPluginModernizer\Modernize\Traits\Plugin;

trait Activation {
    public function ensurePluginActivated() {
        if (!get_option('wppluginmodernizer_activated', false)) {
            throw new \Exception("WPPluginModernizer is not activated.");
        }
    }
}

