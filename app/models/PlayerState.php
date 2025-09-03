<?php
class PlayerState {
    private $stateFile;
    
    public function __construct() {
        $this->stateFile = __DIR__ . '/../../config/player_state.json';
    }
    
    public function getState() {
        if (!file_exists($this->stateFile)) {
            return [
                'playing' => false,
                'title' => null,
                'position' => 0,
                'duration' => 0,
                'repeat' => false,
                'id' => null,
                'start_time' => 0
            ];
        }
        
        $content = file_get_contents($this->stateFile);
        return json_decode($content, true) ?: [
            'playing' => false,
            'title' => null,
            'position' => 0,
            'duration' => 0,
            'repeat' => false,
            'id' => null,
            'start_time' => 0
        ];
    }
    
    public function setState($state) {
        file_put_contents($this->stateFile, json_encode($state));
    }
    
    public function clearState() {
        if (file_exists($this->stateFile)) {
            unlink($this->stateFile);
        }
    }
}