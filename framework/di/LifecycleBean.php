<?php
namespace Framework\Di;

interface LifecycleBean extends Bean {
    public function destroy();
}