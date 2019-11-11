<?php
class block_personalhtml extends block_base {
    public function init() {
        $this->title = get_string('personalhtml', 'block_personalhtml');
    }
    public function get_content(){
        global $COURSE;
        if ($this->content !==null) {
            return $this->content;
        }
        $this->content          = new stdClass;
        $this->content->text    = 'Das Wort unten bewirkt Magie!';
        $this->content->text    = html_writer::link(new moodle_url('/course/view.php?id=4', ['id=4' => $COURSE->id]), "Zum Deutschkurs A1");
        $this->content->footer  = html_writer::link(new moodle_url('/course/view.php?id=3', ['id=3' => $COURSE->id]), "Zur Weiterleitung");

        return $this->content;
        if(! empty($this->config->text)) {
            $this->content->text = $this->config->text;
        }
    }
    public function specialization() {
        if (isset($this->config)) {
            if (empty($this->config->title)) {
                $this->title = get_string('defaulttitle', 'block_personalhtml');
            } else {
                $this->title = $this->config->title;
            }

            if (empty($this->config->text)) {
                $this->config->text = get_string('defaulttext', 'block_personalhtml');
            }
        }
    }
    public function instance_allow_multiple() {
        return true;
    }

}