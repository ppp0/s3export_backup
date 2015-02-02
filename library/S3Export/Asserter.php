<?php

class S3Export_Asserter {

    /** @var int */
    private $_assertionSuccessCount;

    /** @var int */
    private $_assertionFailCount;

    public function __construct() {
        $this->_assertionSuccessCount = 0;
        $this->_assertionFailCount = 0;
    }

    /**
     * @param mixed         $assertion
     * @param callable|null $onSuccess
     * @param callable|null $onFailure
     */
    public function assertThat($assertion, $onSuccess = null, $onFailure = null) {
        if ($assertion) {
            $this->_assertionSuccessCount++;
            if (null !== $onSuccess) {
                $onSuccess();
            }
        } else {
            $this->_assertionFailCount++;
            if (null !== $onFailure) {
                $onFailure();
            }
        }
    }

    /**
     * @return int
     */
    public function getAssertionSuccessCount() {
        return $this->_assertionSuccessCount;
    }

    /**
     * @return int
     */
    public function getAssertionFailCount() {
        return $this->_assertionFailCount;
    }

    /**
     * @return int
     */
    public function getAssertionCount() {
        return $this->getAssertionSuccessCount() + $this->getAssertionFailCount();
    }
}
