from pydantic import BaseModel


class CareerRoleCreate(BaseModel):
    slug: str
    title: str
    description: str | None = None
    required_skills: list[str]
    weeks_template: int

class CareerRoleResponse(BaseModel):
    id: int
    slug: str
    title: str
    description: str | None
    required_skills: list[str]
    weeks_template: int

    model_config = {
        "from_attributes": True
    }