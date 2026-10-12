<?php
namespace UOP\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use UOP\Domain\Profiles\FieldRules;
use UOP\Domain\Forms\FormSchema;
use UOP\Domain\Conditions\ConditionEngine;

final class M3DomainTest extends TestCase {
    public function test_v1_type_palette_and_typed_values(): void {
        self::assertCount(11, FieldRules::types());
        self::assertSame([], FieldRules::normalize('text', null));
        self::assertSame([['slot'=>'value_boolean','value'=>0,'ordinal'=>0]], FieldRules::normalize('checkbox', false));
        self::assertSame([['slot'=>'value_string','value'=>'','ordinal'=>0]], FieldRules::normalize('text', ''));
        self::assertSame([['slot'=>'value_date','value'=>'2028-02-29','ordinal'=>0]], FieldRules::normalize('date', '2028-02-29'));
        self::assertSame([['slot'=>'value_decimal','value'=>'0.000001','ordinal'=>0]], FieldRules::normalize('number', '0.000001'));
        self::assertSame(
            [['slot'=>'value_string','value'=>'red','ordinal'=>0],['slot'=>'value_string','value'=>'blue','ordinal'=>1]],
            FieldRules::normalize('multiselect', ['red','blue'], ['red','blue'])
        );
    }

    public function test_value_validation_rejects_ambiguous_or_illegal_input(): void {
        $cases=[
            ['checkbox','false',[]],
            ['number','1e60',[]],
            ['number','999999999999999.0',[]],
            ['date','2027-02-29',[]],
            ['select','hidden', ['allowed']],
            ['multiselect',['red','red'], ['red']],
            ['consent',true,[]],
            ['email','not-an-email',[]],
        ];
        foreach ($cases as [$type,$value,$choices]) {
            try { FieldRules::normalize($type,$value,$choices); self::fail('Accepted invalid '.$type); }
            catch (InvalidArgumentException) { self::assertTrue(true); }
        }
    }

    public function test_definition_rejects_unknown_attributes_and_privacy_flags(): void {
        $valid=['key'=>'dob','type'=>'date','label'=>'Date of birth','sensitivity'=>'personal','subject_view'=>true];
        FieldRules::validate($valid);
        foreach ([
            $valid+['status'=>'active'],
            array_replace($valid,['subject_view'=>1]),
            array_replace($valid,['type'=>'hidden']),
            array_replace($valid,['sensitivity'=>'unclassified']),
        ] as $bad) {
            try { FieldRules::validate($bad); self::fail('Invalid definition accepted'); }
            catch (InvalidArgumentException) { self::assertTrue(true); }
        }
    }

    public function test_conditions_age_at_event_and_missing_data(): void {
        $engine=new ConditionEngine();
        $ast=['schema_version'=>1,'all'=>[
            ['source'=>'profile','field'=>'date_of_birth','operator'=>'age_lt_at','value'=>18,'context'=>'event.start'],
            ['not'=>['source'=>'registration','field'=>'participant_type','operator'=>'eq','value'=>'staff']],
        ]];
        $facts=['profile'=>['date_of_birth'=>'2010-10-25'],'registration'=>['participant_type'=>'guest']];
        self::assertTrue($engine->evaluate($ast,$facts,['event.start'=>'2026-10-25T10:00:00+00:00']));
        $facts['registration']['participant_type']='staff';
        self::assertFalse($engine->evaluate($ast,$facts,['event.start'=>'2026-10-25T10:00:00+00:00']));
        self::assertFalse($engine->evaluate($ast,['profile'=>[],'registration'=>[]],['event.start'=>'2026-10-25T10:00:00+00:00']));
    }

    public function test_ast_schema_and_operator_fail_closed(): void {
        $engine=new ConditionEngine();
        foreach ([
            ['schema_version'=>1,'all'=>[]],
            ['schema_version'=>2,'all'=>[['source'=>'profile','field'=>'age','operator'=>'exists']]],
            ['schema_version'=>1,'source'=>'profile','field'=>'a','operator'=>'eval'],
            ['schema_version'=>1,'not'=>['source'=>'profile','field'=>'a','operator'=>'exists','php'=>'system']],
            ['schema_version'=>1,'all'=>[['source'=>'profile','field'=>'a','operator'=>'age_lt_at','value'=>18]]],
        ] as $invalid) {
            try { $engine->validate($invalid); self::fail('Invalid AST accepted'); }
            catch (InvalidArgumentException) { self::assertTrue(true); }
        }
    }

    public function test_age_predicates_cannot_be_true_when_evidence_is_unknown_and_negated(): void {
        $engine = new ConditionEngine();
        $predicate = ['source'=>'profile','field'=>'date_of_birth','operator'=>'age_lt_at','value'=>18,'context'=>'event.start'];
        $negated = ['schema_version'=>1,'not'=>$predicate];
        $nested = ['schema_version'=>1,'not'=>['any'=>[
            $predicate,
            ['source'=>'registration','field'=>'nickname','operator'=>'eq','value'=>'known'],
        ]]];
        $all = ['schema_version'=>1,'all'=>[
            $predicate,
            ['source'=>'registration','field'=>'nickname','operator'=>'eq','value'=>'known'],
        ]];
        $event = ['event.start'=>'2030-05-01 12:00:00'];
        foreach ([[], ['date_of_birth'=>null], ['date_of_birth'=>'not-a-date'], ['date_of_birth'=>'2040-01-01']] as $profile) {
            $facts = ['profile'=>$profile,'registration'=>['nickname'=>'other']];
            self::assertFalse($engine->evaluate($negated,$facts,$event), 'A missing DOB became true through NOT.');
            self::assertFalse($engine->evaluate($nested,$facts,$event), 'A missing DOB became true through NOT/ANY.');
            self::assertFalse($engine->evaluate($all,$facts,$event), 'A missing DOB became true through ALL.');
        }
        self::assertFalse($engine->evaluate($negated,['profile'=>['date_of_birth'=>'2015-02-28'],'registration'=>[]],[]), 'Missing event date became a grant.');
        self::assertTrue($engine->evaluate($negated,['profile'=>['date_of_birth'=>'2000-01-01'],'registration'=>[]],$event));
        self::assertFalse($engine->evaluate($negated,['profile'=>['date_of_birth'=>'2015-01-01'],'registration'=>[]],$event));
    }

    public function test_age_operator_only_accepts_reserved_authoritative_profile_field(): void {
        $engine=new ConditionEngine();
        foreach ([
            ['source'=>'registration','field'=>'date_of_birth','operator'=>'age_gte_at','value'=>18,'context'=>'event.start'],
            ['source'=>'profile','field'=>'birthday','operator'=>'age_gte_at','value'=>18,'context'=>'event.start'],
            ['source'=>'profile','field'=>'age','operator'=>'age_lt_at','value'=>18,'context'=>'event.start'],
        ] as $condition) {
            try { $engine->validate(['schema_version'=>1,'not'=>$condition]); self::fail('Age operator accepted a user-controlled fact.'); }
            catch (InvalidArgumentException) { self::assertTrue(true); }
        }
    }

    public function test_reserved_age_field_must_be_explicitly_non_editable(): void {
        $safe=['key'=>'date_of_birth','type'=>'date','label'=>'Date of birth','subject_view'=>true,'subject_edit'=>false,'delegate_edit'=>false];
        FieldRules::validate($safe);
        foreach ([
            array_replace($safe,['subject_edit'=>true]),
            array_replace($safe,['delegate_edit'=>true]),
            array_replace($safe,['type'=>'text']),
            ['key'=>'date_of_birth','type'=>'date','label'=>'DOB'],
        ] as $invalid) {
            try { FieldRules::validate($invalid); self::fail('Self-attested age field definition was accepted.'); }
            catch (InvalidArgumentException) { self::assertTrue(true); }
        }
        FieldRules::validate(['key'=>'nickname','type'=>'text','label'=>'Name']); // Existing V1 self-edit defaults are unaffected.
    }

    public function test_verified_age_requires_evidence_context_and_ignores_claimed_birthdate(): void {
        $engine = new ConditionEngine();
        foreach (['age_verified_gte_at','age_verified_lt_at'] as $operator) {
            $node = ['source'=>'profile','field'=>'date_of_birth','operator'=>$operator,'value'=>18,'context'=>'event.start'];
            $condition = ['schema_version'=>1,'not'=>$node];
            $facts = ['profile'=>['date_of_birth'=>'1980-01-01'],'registration'=>[]];
            self::assertFalse($engine->evaluate($condition,$facts,['event.start'=>'2030-01-01 00:00:00']), 'NOT must not turn missing independent evidence into a grant.');
            $key = 'age.verified.' . ('age_verified_gte_at' === $operator ? 'gte' : 'lt') . '.18';
            self::assertTrue($engine->evaluate(['schema_version'=>1,'all'=>[$node]],$facts,[$key=>'verified','event.start'=>'2030-01-01 00:00:00']));
            self::assertFalse($engine->evaluate($condition,$facts,[$key=>'verified','event.start'=>'2030-01-01 00:00:00']));
        }
        foreach (['registration','self'] as $source) {
            try {
                $engine->validate(['schema_version'=>1,'all'=>[['source'=>$source,'field'=>'date_of_birth','operator'=>'age_verified_gte_at','value'=>18,'context'=>'event.start']]]);
                self::fail('Untrusted verified age predicate source was accepted.');
            } catch (InvalidArgumentException) { self::assertTrue(true); }
        }
    }

    public function test_form_conditions_reject_missing_consent_and_cyclic_dependencies(): void {
        $schema=new FormSchema();
        $predicate=static fn(string $reference): array => [
            'schema_version'=>1,
            'all'=>[['source'=>'registration','field'=>$reference,'operator'=>'exists']]
        ];
        $valid=['schema_version'=>1,'fields'=>[
            ['key'=>'first','type'=>'text','label'=>'First','required'=>false,'visible_when'=>$predicate('second')],
            ['key'=>'second','type'=>'text','label'=>'Second','required'=>false],
        ]];
        $schema->validate_draft($valid); // Forward references are legal when acyclic.

        $missing=$valid;
        $missing['fields'][0]['visible_when']=$predicate('deleted');
        $self=$valid;
        $self['fields'][0]['visible_when']=$predicate('first');
        $cycle=$valid;
        $cycle['fields'][1]['visible_when']=$predicate('first');
        $consent=$valid;
        $consent['fields'][1]=[
            'key'=>'second','type'=>'consent','label'=>'Consent','required'=>false,
            'consent_definition_public_id'=>\UOP\Core\PublicId::generate()->to_string()
        ];
        $nested=$valid;
        $nested['fields'][0]['visible_when']=[
            'schema_version'=>1,
            'not'=>['any'=>[
                ['source'=>'registration','field'=>'deleted','operator'=>'exists'],
                ['source'=>'profile','field'=>'birth_date','operator'=>'exists']
            ]]
        ];
        foreach ([$missing,$self,$cycle,$consent,$nested] as $invalid) {
            try {
                $schema->validate_draft($invalid);
                self::fail('Dangling or circular form visibility dependency was accepted.');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

}
